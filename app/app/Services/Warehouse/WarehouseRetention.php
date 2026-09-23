<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

/**
 * The horizon on the derived warehouse — `GOAIEZ_PIXEL_MASTER_BUILD` §18's
 * *"L0 7 years; L1 400 days"* and §5.4's *"Retention | 400d / 24mo"*
 * (decisions 7700–7719).
 *
 * ## ⛔ WHAT THIS CLOSES: THE ACCEPTED PATH HAD NO BOUND OF ANY KIND
 *
 * Wave 11 gave `ingest_rejects` a ninety-day horizon (7620–7639) — the table
 * that records the traffic this platform **refuses**. Nothing bounded the
 * traffic it **accepts**. `MonthlyEventCap::admit()` implements §11 row 4
 * faithfully — *"At cap: pageviews continue, others dropped"* — so a batch whose
 * events are all `pageview` is admitted past `pixel.free_event_cap_monthly`
 * unconditionally, and `PixelRateLimits::BEACONS_PER_MINUTE` is 300 **per
 * source**, times `StorePixelBatchRequest::MAX_EVENTS` of 50. That is 21.6
 * million events a day from one host holding one public key, into a table
 * nothing had ever deleted a row from except a tenant erasure.
 *
 * ⚠️ **AND THE STORE THAT GREW WAS NOT THE STORE ANYBODY WOULD LOOK AT.** The
 * reject table at least has a bell on it. Accepted traffic writes no reject row,
 * so §5.7's alert never fires, and the only artefact that moves is a row count
 * in Postgres nothing measures — 7634(d)'s finding, arriving on the other path.
 *
 * ## ⛔ WHY A HORIZON HERE IS A DIFFERENT KIND OF DECISION FROM ONE ON L0
 *
 * `CLAUDE.md`, critical rules: *"L0 is immutable and replayable; L1/L2 must be
 * truncatable and rebuildable byte-identically."* §5.1 says the same thing from
 * the other side — L1 and L2 are *"derived and disposable"*. **So nothing here
 * destroys a record of truth.** Every byte these rows were derived from is still
 * in L0 under its own object, and `warehouse:replay --tenant=… --from=… --to=…`
 * rebuilds any range this sweep removed. That is exactly the argument
 * [[PhiExclusion::purgeDerivedFor()]] already makes for deleting the same rows
 * on a different trigger, and it is why the same operation would be indefensible
 * one layer down.
 *
 * ⛔ **L0 IS THEREFORE NOT PRUNED HERE AND NOT PRUNED ANYWHERE, AND THAT IS
 * STATED RATHER THAN LEFT TO BE FOUND** (7706). §5.2's *"Retention: 7 years"* is
 * a sentence in a document: there is no bucket lifecycle rule in this
 * repository, no config key beside `warehouse.l0_disk` and
 * `warehouse.schema_version`, no [[\App\Enums\StoredObjectKind]] case — so
 * `StorageRetention` and `storage:prune` cannot reach it — and the only deletion
 * path that exists is [[\App\Contracts\L0Archive::purgeFor()]], called from
 * tenant erasure. **The honest statement is stronger than seven years: nothing
 * deletes L0 at seven years either.** A period on L0 is an owner's ruling and a
 * bucket-lifecycle decision, not a constant a lane may pick — see the decision
 * rows.
 *
 * ## ⚠️ THE TWO HORIZONS ARE ORDERED, AND THE ORDER IS THE SAFETY PROPERTY
 *
 * §18: *"L1 400 days; **roll up to L2 before TTL expiry or benchmarks silently
 * degrade**"*. A mart that expired **before** its source would be rebuilt from
 * an L1 range that no longer exists, so {@see self::prune()} clamps the L2 cut
 * so it can never be earlier than the L1 cut, whatever the two figures say. That
 * is `IngestRejects::prune()`'s clamp for a different reader: a property of the
 * method rather than a range the constants are trusted to stay inside.
 *
 * ⛔ **AND THE ROLL-UP ITSELF IS NOT SCHEDULED, WHICH IS SOMEBODY ELSE'S TO FIX
 * AND IS WRITTEN DOWN HERE BECAUSE THIS IS WHERE IT BITES** (7707). The only
 * writer of any L2 mart is [[Replayer]], and the only caller of `Replayer` is
 * the `warehouse:replay` command, which nothing in `routes/console.php` runs.
 * So on a live deployment L2 is populated by an operator typing a command and by
 * nothing else. This sweep is safe under that regime — it deletes L2 rows that
 * do not exist — and the sentence §18 warns about is already true for a reason
 * that has nothing to do with a horizon.
 *
 * ⛔ **AND SCHEDULING ONE IS BLOCKED ON A DERIVATION DEFECT RATHER THAN ON A
 * COST — FOUND 2026-08-22 (7840–7859), AND THIS IS WHERE THE SECOND HALF OF IT
 * LANDS.** `l2_fact_session.day` is `date_trunc('day', min(received_at))` taken
 * over **the range being replayed**, so the same L0 produces a different mart
 * depending on how the range was cut: a session straddling UTC midnight is one
 * row on its first day when both days are replayed together and two rows when
 * they are replayed separately. Any forward roll-up would therefore be a second
 * derivation that disagrees with `warehouse:replay` at its own trailing edge, on
 * the marts `28` §4.3's rollback triggers read.
 * `tests/Feature/Warehouse/IncrementalRollUpTest.php` proves it on this
 * repository's own replay fixture. **Describe the defect, not the remedy**: the
 * fix changes what a session *is* across four marts and two build-failing gates,
 * and it is not a lane's to pick.
 *
 * ⚠️ **THE OWNER PICKED IT ON 2026-08-22 AND THE PARAGRAPH ABOVE IS HALF TRUE —
 * BOTH READINGS KEPT** (8040, 8041). `l2_fact_session.day` **does not exist**: a
 * session is one row keyed `(business_id, session_id)`, so the two-rows-versus-one
 * split it describes cannot be produced by any cut. ⛔ **The conclusion is
 * unchanged and that is the part to read twice**: `scoped` is still the range, so
 * a piecewise replay still produces a different session row from a whole-range one
 * — it now describes only the last piece replayed instead of splitting into two —
 * and a forward roll-up would still be a second derivation disagreeing with
 * `warehouse:replay` at its trailing edge. **Scheduling is no more available than
 * it was.**
 *
 * ## ⛔ AND THIS SWEEP MAKES ONE DERIVED COLUMN A FUNCTION OF THE CLOCK
 *
 * ⛔ **`l2_fact_session.is_new` READS EVERY L1 ROW THE TENANT HAS, NOT JUST THE
 * RANGE — SO DELETING L1 CHANGES WHAT A LATER REPLAY OF AN UNTOUCHED RANGE
 * SAYS** (7840–7859). [[Replayer]]'s `NOT EXISTS (… received_at < …)` asks
 * whether this visitor was ever seen before; once this sweep has removed the
 * days in which they were, the answer flips and a returning visitor is derived
 * as new. **Same L0, same command, different bytes — decided by whether this
 * ran.** `CLAUDE.md`'s critical rule is *"L1/L2 must be truncatable and
 * rebuildable byte-identically"*, and the honest statement is narrower than
 * that: a replay of a range is a function of that range's L0 **and of whatever
 * L1 survives before it**. ⚠️ **This horizon is what made that matter** —
 * before 7700 nothing had ever deleted an `l1_events` row except a tenant
 * erasure — so it is recorded on the sweep that created the condition and not
 * only on the derivation that has it. ⚠️ **`ByteIdenticalReplayTest` cannot see
 * it**, because it replays a range whose predecessors it never prunes;
 * `IncrementalRollUpTest` drives it directly.
 */
final class WarehouseRetention
{
    /**
     * How long a conformed event is kept — §18's *"L1 400 days"*, and §5.4's
     * `pii` column, *"400d / 24mo"*.
     *
     * ⚠️ **READ OFF THE GOVERNING DOCUMENT RATHER THAN CHOSEN, WHICH IS WHY IT
     * IS A CONSTANT AND NOT A WITHHELD REGISTRY ROW.** `storage.retention_days.*`
     * is the precedent for the other direction (4941, 4942): those four periods
     * are the owner's because they govern **other people's uploaded files** and
     * this platform had published no commitment about any of them. This one is
     * different in exactly that respect — `GOAIEZ_PIXEL_MASTER_BUILD` is
     * authoritative for its own scope (`29` §0.2) and states the number twice,
     * at §5.4 and at §18. Picking a different figure would be the lane
     * overruling the spec; withholding it would be refusing to enforce a period
     * the spec already fixed.
     *
     * ⚠️ **IT IS NOT A PRIVACY PERIOD AND MUST NOT BE WRITTEN UP AS ONE.** The
     * same events are in L0 for as long as L0 lasts, which is for ever today, so
     * no horizon here shortens what this platform holds about anybody. **It is a
     * volume bound** — `IngestRejects::RETENTION_DAYS`' own distinction, and the
     * claim the next reader would otherwise make on this constant's behalf.
     */
    public const int L1_RETENTION_DAYS = 400;

    /**
     * How long a derived mart row is kept — §5.4's *"24mo"*.
     *
     * ⚠️ **730 RATHER THAN 720 OR 731.** Two years of days, which is what a
     * rollup keyed on a `day` column can express exactly; the spec's unit is
     * months and the column's is days, and rounding a month to 30 is the kind of
     * silent approximation `CLAUDE.md`'s *verify against the raw artefact* rule
     * is about. A row is removed on the first sweep after it is 730 days old,
     * never before.
     *
     * ⚠️ **LONGER THAN L1, AND {@see self::prune()} ENFORCES THAT RATHER THAN
     * TRUSTING IT.** A mart exists so a question can be answered after its
     * source is gone.
     */
    public const int L2_RETENTION_DAYS = 730;

    public static function l2RetentionDays(): int
    {
        return app(DefaultsRegistry::class)->int('warehouse.l2_retention_days');
    }

    /**
     * Rows per DELETE — `PrunePublicAudits`' figure, and the table this matters
     * most on.
     *
     * ⛔ **`l1_events` IS THE ONE TABLE IN THIS SCHEMA WHOSE ROW COUNT IS A
     * FUNCTION OF STRANGERS' PAGE LOADS.** A single unbounded DELETE over four
     * hundred days of it would hold locks on the table the live ingest path
     * inserts into on every accepted beacon, which is the one thing a retention
     * sweep must never do.
     */
    private const int CHUNK = 500;

    /**
     * Delete this tenant's expired derived rows, and say how many per layer.
     *
     * ⛔ **PER TENANT, AND ON THIS TABLE THAT IS NOT A STYLE CHOICE** (7626's
     * finding, one table over). Every derived table is `ENABLE`+`FORCE` row-level
     * secured on `app.business_id` and the application connects as a non-owner
     * role, so a single range DELETE issued from a console command with no tenant
     * set **matches zero rows and exits 0**. A pruner is the worst possible host
     * for that failure: *"deleted nothing"* is also what a healthy night looks
     * like, so the broken sweep and the working one print the same sentence. The
     * enumeration is [[\App\Console\Commands\PruneWarehouse]] — the eighth owner
     * walk — and this method is only ever reached inside `Tenancy::actingAs()`.
     *
     * ## ⛔ THE L1 CUT IS THE EARLIER OF THE HORIZON AND A LIVE READER'S WINDOW
     *
     * [[PixelSightings]] reads `l1_events` directly — ⚠️ **"IT IS THE ONLY READER
     * IN THIS APPLICATION THAT DOES" WAS TRUE AND IS NOT, AS OF 2026-08-22
     * (7984, 8022): `PixelArrivals` is the second**, and it reads the same table
     * for the tenant-facing *"we are receiving your data"* answer. ⛔ **The clamp
     * below is unaffected and the reason is worth stating rather than assuming**:
     * the second reader's window is the full 400-day horizon, so it needs no
     * floor of its own and cannot be silently downgraded by a shorter cut the
     * way a 30-day window can. — and answers *"has this
     * tenant's pixel been seen on this host in the last
     * {@see PixelSightings::FRESH_DAYS} days"*, which `ActuationTiers` turns into
     * a T3 claim about a site we can reach. **A horizon shorter than that window
     * would silently downgrade every tenant's actuation tier**, and it would do
     * it the way this codebase's worst defects do it: with a green suite, a
     * cheerful sweep and a screen that is merely wrong. So no row inside that
     * window is reachable from here, whatever `$l1KeepDays` says —
     * `IngestRejects::prune()`'s clamp, for a different reader.
     *
     * ⚠️ **THE CLAMP IS ARITHMETIC ON TWO CONSTANTS AND CANNOT FAIL**, which is
     * where this and its precedent both depart from `OperatorAlerts::prune()`:
     * that one puts its floor inside the `try` because the floor is a registry
     * read that can throw. There is no registry key here. **A future registry
     * key for either horizon belongs inside the `try` on that precedent's
     * argument exactly.**
     *
     * ## ⛔ CONTAINED AND COUNTED, BECAUSE THIS IS A WALK
     *
     * A failed prune warns and is counted. `ExecuteTenantDeletions`' rule: an
     * unreachable row for tenant seventeen must not stop tenants eighteen onward
     * being swept, and a command that reddens on one tenant's lock contention is
     * one whose red is ignored inside a week.
     *
     * ⛔ **THIS DOCBLOCK SAID *"A FAILED PRUNE RETURNS ZERO FOR THAT LAYER … `0`
     * IS THEREFORE THE ONE NUMBER THIS METHOD CANNOT TELL APART FROM A TENANT
     * WITH NOTHING TO DELETE"* UNTIL 2026-08-22, AND STATING THE COST WAS NOT
     * THE SAME AS PAYING IT** (7840–7859). It was decision **1993** exactly, one
     * pruner along: `ExportBuilder::purgeExpired()` swallowed a `Throwable` and
     * returned only the count removed, so `exports:prune` printed *"No expired
     * exports to prune."* — **affirmatively false** — while §3.7's promise failed
     * for every tenant at once. Here the same shape produced *"No derived
     * warehouse rows past their horizon across N accounts."* for a night on which
     * every DELETE raised. ✅ **{@see self::sweep()} returns `null` rather than
     * `0` when the delete could not run**, so the ambiguity is gone from the type
     * rather than from a comment, and the count travels out to
     * [[\App\Console\Commands\PruneWarehouse]], which warns on it.
     *
     * ⚠️ **THE EXIT CODE STAYS `0`, ON `PruneTenantExports`' OWN ARGUMENT** — a
     * lock contention is transient and *"a command that reddens on a transient
     * condition is one whose red is ignored inside a week"*. ⛔ **What is still
     * true is that nothing watches the warning**: the scheduled run's output goes
     * to cron, `routes/console.php` wires no `onFailure()` anywhere, and a bell
     * needs an `OperatorAlertKind`. **Owed, and named in the decision rows.**
     *
     * ## ⚠️ WHAT A CONCURRENT REPLAY DOES, SINCE THE BRIEF ASKED
     *
     * [[Replayer::replay()]] deletes a tenant's range and rebuilds it from L0 in
     * one transaction. This sweep and that transaction can only contend on rows,
     * never corrupt: the replay's inserts are invisible until it commits, and a
     * range delete either blocks or is blocked. **What a replay of an expired
     * range does is resurrect it**, because a replay is a function of L0 and L0
     * still holds every line — and the next run of this sweep removes it again.
     * That is deliberate rather than tolerated: an operator replaying a
     * four-hundred-day-old range is investigating something, and a replay that
     * silently returned fewer rows than L0 holds would be the more damaging
     * behaviour.
     *
     * @param  int  $l1KeepDays  {@see self::L1_RETENTION_DAYS}, passed by the
     *                           caller rather than read here, on
     *                           `IngestRejects::prune()`'s shape.
     * @param  int  $l2KeepDays  {@see self::L2_RETENTION_DAYS}, likewise.
     * @return array{l1: int, l2: int, failed: int} rows removed per layer, and
     *                                              the number of tables whose
     *                                              delete could not run at all
     */
    public function prune(int $l1KeepDays, int $l2KeepDays, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        $deleted = ['l1' => 0, 'l2' => 0, 'failed' => 0];

        $horizon = $now->utc()->subDays(max(1, $l1KeepDays));
        $reader = $now->utc()->subDays(PixelSightings::FRESH_DAYS);

        $l1Cut = $horizon->lessThan($reader) ? $horizon : $reader;

        // A mart may never expire before the layer it was derived from — see the
        // class docblock. Derived from the L1 cut itself rather than from
        // `$l1KeepDays`, so the clamp above cannot be undone by this one.
        $l2Horizon = $now->utc()->subDays(max(1, $l2KeepDays));

        $l2Cut = $l2Horizon->lessThan($l1Cut) ? $l2Horizon : $l1Cut;

        foreach (DerivedTables::DERIVED_TABLES as $layer => $tables) {
            foreach ($tables as $table) {
                $swept = $this->sweep(
                    $table,
                    $this->columnFor($table),
                    $layer === 'l1' ? $l1Cut : $l2Cut->toDateString(),
                );

                // ⛔ `null` IS NOT `0` HERE AND THAT IS THE WHOLE OF THE 2026-08-22
                // CHANGE. A table with nothing to delete and a table whose delete
                // raised used to add the same number to the same total, which is
                // what let the command's summary say "nothing was past its
                // horizon" about a night on which nothing could be reached.
                if ($swept === null) {
                    $deleted['failed']++;

                    continue;
                }

                $deleted[$layer] += $swept;
            }
        }

        return $deleted;
    }

    /**
     * The time column each derived table expires on.
     *
     * ⛔ **L1 EXPIRES ON `received_at` AND NEVER ON `occurred_at`, WHICH IS
     * [[Replayer::rebuildL1()]]'s OWN RULE FOR ITS OWN RANGE DELETE.** A client's
     * clock decides `occurred_at`, so a browser with a wrong one would put its
     * events outside every range that could ever remove them — a row that is
     * permanently unexpirable because somebody's laptop is set to 2041.
     * `received_at` is stamped by [[\App\Services\Pixel\PixelCollector]] at
     * receipt and archived in L0, so it is both ours and reproducible.
     *
     * ⛔ **"EVERY MART EXPIRES ON `day`" WAS TRUE AND IS NOT — CORRECTED
     * 2026-08-22 (8040).** That sentence sat above a `match` on the **layer**,
     * and the owner's ruling took `day` off `l2_fact_session` — so a per-layer
     * answer would have swept that mart on a column it no longer has. ⛔ **The
     * failure would have been silent in production and loud only in the suite**:
     * {@see self::sweep()} catches `Throwable`, so an *undefined column* becomes
     * a warning line and a `null`, the command exits `0`, and the session mart
     * grows for ever. ✅ **So the answer is per table rather than per layer**, and
     * a table with no entry raises here — outside the `try`, where it cannot be
     * swallowed.
     *
     * ⚠️ **`l2_fact_session` EXPIRES ON `first_received_at` AND NOT ON
     * `started_at`**, which is the same argument as L1's above rather than a new
     * one: `started_at` is `min(occurred_at)`, a client clock, so a horizon keyed
     * on it is unreachable for a laptop set to 2041 and immediate for one set to
     * 2001. `first_received_at` is ours. ⚠️ **A `timestamp` compared against the
     * L2 cut's date string is midnight of that date**, which is the boundary the
     * `day` columns already expire on, so the two layers stay comparable.
     *
     * @var array<string, string>
     */
    public const array EXPIRES_ON = [
        'l1_events' => 'received_at',
        'l2_fact_conversion' => 'day',
        'l2_fact_daily_tenant' => 'day',
        'l2_fact_page_daily' => 'day',
        'l2_fact_session' => 'first_received_at',
        'l2_fact_source_daily' => 'day',
        'l2_fact_vital_daily' => 'day',
    ];

    /**
     * The time column one derived table expires on.
     *
     * ⚠️ **CALLED FROM {@see self::prune()} AND DELIBERATELY OUTSIDE
     * {@see self::sweep()}'s `try`.** A table nobody chose a column for must stop
     * the walk rather than be logged as one more unreachable table, because the
     * two look identical in the count and only one of them is a code defect.
     */
    private function columnFor(string $table): string
    {
        return self::EXPIRES_ON[$table] ?? throw new LogicException(
            'The derived warehouse gained a "'.$table.'" table and `WarehouseRetention::EXPIRES_ON` '
            .'does not say which column its rows expire on. Add it deliberately: `received_at`, `day` '
            .'and `first_received_at` are not interchangeable, and a column the table does not have is '
            .'swallowed by sweep() as a warning — a mart that is never pruned while the command exits 0.'
        );
    }

    /**
     * One table's chunked delete, contained.
     *
     * ⚠️ **`DB::table()` RATHER THAN THE MODEL, WITH `business_id` WRITTEN OUT** —
     * [[PhiExclusion::purgeDerivedFor()]]'s shape, and for its reason: the
     * subject is a list of table names rather than a list of models, so there is
     * no global scope to lean on and the predicate is explicit with row-level
     * security under it. ⛔ **Postgres compiles a limited DELETE to
     * `where ctid in (select … limit N)`**, and the inner select carries the
     * `business_id` predicate, so the chunking cannot widen the statement.
     *
     * ⛔ **`null` MEANS THE DELETE COULD NOT RUN, AND IT IS A DIFFERENT ANSWER
     * FROM `0`** (7840–7859). This returned `int` until 2026-08-22, so a table
     * that raised was indistinguishable from a table with nothing in it — 1993's
     * defect, in the ninth pruner, with the swallowed `Throwable` in this method
     * rather than in an object-store adapter.
     *
     * @return int|null rows removed, or `null` where the delete raised
     */
    private function sweep(string $table, string $column, CarbonImmutable|string $cut): ?int
    {
        try {
            $businessId = Tenancy::idOrFail();

            $deleted = 0;

            do {
                $batch = DB::table($table)
                    ->where('business_id', $businessId)
                    ->where($column, '<', $cut)
                    ->limit(self::CHUNK)
                    ->delete();

                $deleted += $batch;
            } while ($batch === self::CHUNK);

            return $deleted;
        } catch (Throwable $e) {
            Log::warning('a derived warehouse table could not be pruned', [
                'business_id' => Tenancy::id(),
                'table' => $table,
                'exception' => $e::class,
            ]);

            return null;
        }
    }
}
