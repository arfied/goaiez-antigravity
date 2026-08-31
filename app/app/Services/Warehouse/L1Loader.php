<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The one INSERT into `l1_events`, used by live ingest and by a replay alike.
 *
 * ⛔ **IT EXISTS BECAUSE TWO WRITERS OF ONE TABLE IS TWO DERIVATIONS THAT AGREE
 * UNTIL THEY DO NOT.** `Replayer::rebuildL1()` was the only writer while the
 * collector was unbuilt; [[\App\Jobs\ArchivePixelBatchJob]] is the second, and a
 * chunk size or a conflict clause that differed between them would produce a
 * warehouse where **replaying a range changes rows nobody touched** — which
 * `ByteIdenticalReplayTest` cannot see, because both of its runs go through the
 * replay path. So the insert is here and both callers use it.
 *
 * ⚠️ **DELIBERATELY NOT THE DELETE.** `Replayer` deletes a tenant's range before
 * rebuilding it and live ingest must never delete anything; folding both into one
 * "write" method would put a `DELETE` one boolean away from the request path, and
 * that boolean is exactly the kind of thing a later edit gets backwards. The
 * range delete stays in `Replayer`, with its own reasoning about `TRUNCATE` and
 * row-level security (decision 4869).
 *
 * ⛔ **RULE 24's WAREHOUSE REFUSAL WAS HERE AND IS GONE — THE OWNER OVERRODE
 * THE RULE AND ITS §12.1 LINE ON 2026-08-30** (12532, 12539). What stood here
 * was `PhiExclusion::refuse()`, called once per distinct `business_id` in the
 * batch.
 *
 * ⛔ **AND REMOVING IT WOULD HAVE TAKEN A TENANCY GUARD WITH IT, WHICH IS WHY
 * THE LOOP SURVIVES WITH A DIFFERENT BODY.** The refusal read
 * `businesses.data_classification` under FORCE row-level security, so it threw
 * a named `LogicException` whenever a row carried somebody else's
 * `business_id` — and this method's own docblock relied on that: *"row-level
 * security would refuse that insert anyway, but with a Postgres policy
 * violation rather than a sentence naming what went wrong."* **That guard has
 * nothing to do with PHI**, it was riding on the class that did, and it is the
 * second coupling this slice found by looking rather than by grepping — the
 * first was the derived-table list, now {@see DerivedTables}. **It is
 * reproduced below on its own terms.**
 */
final class L1Loader
{
    /**
     * How many rows go in one statement.
     *
     * An L0 day can be large and a single multi-row INSERT with a hundred
     * thousand rows exceeds Postgres' bind-parameter limit.
     */
    public const int CHUNK = 500;

    /**
     * Append derived rows, ignoring ones already present.
     *
     * ⚠️ **`insertOrIgnore` IS §11 ROW 5's IDEMPOTENCY AND NOT A CONVENIENCE.**
     * The specification asks for `event_id` dedupe with a 24-hour Redis `SETNX`;
     * what this schema has instead is the primary key `(business_id, event_id)`,
     * which is stronger — it has no expiry, it survives a cache flush, and it
     * works identically for a retried beacon, a retried queue job and a replay
     * of an object whose events also arrived live. **A Redis window would have
     * let a beacon retried after 24 hours insert a duplicate**; this cannot.
     *
     * ⛔ **THIS SAID "THE PRIMARY KEY ON `event_id`" UNTIL 2026-08-20 AND THE
     * MISSING COLUMN WAS A CROSS-TENANT DEFECT RATHER THAN A TYPO — BOTH
     * READINGS KEPT AND DATED** (6182, 6240). `event_id` is minted in the
     * browser, so under the old key every tenant shared one key space, and
     * **a unique index is not filtered by row-level security**: the conflict
     * this method resolves with `DO NOTHING` could be with a row belonging to
     * somebody else, whose existence the acting tenant cannot see. The second
     * tenant to present an id lost the event — counted below, absent from the
     * table, and reported nowhere. ⚠️ **The paragraph's own claim survives the
     * correction intact**, which is why it is kept: a composite key is still
     * stronger than the Redis window, and a conflict can now only be your own
     * row, which is the idempotency the sentence was always describing.
     *
     * ⚠️ **RETURNS THE ROWS ACTUALLY WRITTEN, WHICH IS NOT THE ROWS OFFERED**, and
     * `etl_runs.l1_rows` is derived from it. A caller reading it as "how many
     * events did this batch hold" is reading a number that duplicates subtract
     * from.
     *
     * ⚠️ **THE PRECONDITION IS DERIVED FROM THE ROWS, NOT FROM THE TENANT IN
     * CONTEXT**, and the difference is the point. Reading the context alone
     * would say nothing about a row carrying somebody else's `business_id` —
     * row-level security would refuse that insert anyway, but with a Postgres
     * policy violation rather than a sentence naming what went wrong. Asking
     * per distinct id makes the mismatch a **checked** precondition.
     *
     * ⚠️ **AN EMPTY BATCH ASKS NOTHING AND INSERTS NOTHING.** That is not a
     * hole: there is no row to check.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function insert(array $rows): int
    {
        foreach ($this->businessIds($rows) as $businessId) {
            $this->refuseAnotherTenantsRow($businessId);
        }

        $written = 0;

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            $written += DB::table('l1_events')->insertOrIgnore($chunk);
        }

        return $written;
    }

    /**
     * Refuse a row belonging to a business other than the one in context.
     *
     * ⛔ **THIS IS THE HALF OF `PhiExclusion::handlesHealthInformation()` THAT
     * WAS NEVER ABOUT PHI** (12539). `l1_events` is FORCE row-level security, so
     * the insert would be refused either way — what this adds is a sentence
     * naming the business, at the frame that has it, instead of a Postgres
     * policy violation twenty frames from the batch that caused it.
     *
     * ⚠️ **A `LogicException` AND NOT A VALIDATION FAILURE**, because both
     * callers build their own rows: arriving here means a caller composed a
     * batch across tenants, which is a programming error rather than bad input.
     */
    private function refuseAnotherTenantsRow(int $businessId): void
    {
        $tenant = Tenancy::idOrFail();

        if ($tenant === $businessId) {
            return;
        }

        throw new LogicException(
            'An l1_events batch carries business '.$businessId.' while acting as '.$tenant
            .'. `l1_events` is FORCE row-level security, so this insert would be refused by the '
            .'database with a policy violation naming nothing — the batch was composed across '
            .'tenants and that is the defect to fix.'
        );
    }

    /**
     * Every distinct `business_id` these rows carry.
     *
     * ⚠️ **A ROW WITH NO `business_id` IS NOT SKIPPED — IT CANNOT HAPPEN AND THE
     * COLUMN IS `NOT NULL`, SO SKIPPING ONE WOULD BE A HOLE IN THE GATE WEARING
     * A DEFENSIVE CAST.** `L1Derivation::rows()` always sets it; anything else
     * reaching here is malformed, and `(int) null` is `0`, which matches no
     * business, so {@see self::refuseAnotherTenantsRow()} throws the tenant-mismatch
     * exception rather than letting it pass. Loud is correct.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<int>
     */
    private function businessIds(array $rows): array
    {
        $ids = [];

        foreach ($rows as $row) {
            $id = (int) ($row['business_id'] ?? 0);

            if (! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
