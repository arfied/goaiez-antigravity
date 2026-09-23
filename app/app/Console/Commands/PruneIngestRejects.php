<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\User;
use App\Services\Pixel\IngestRejects;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Delete pixel reject buckets past {@see IngestRejects::RETENTION_DAYS}
 * (7620–7639, closing 5019 and 7545(b)).
 *
 * ## ⛔ WHAT THIS CLOSES, AND WHY THE ROLLUP WAS NOT ALREADY THE ANSWER
 *
 * `ingest_rejects` shipped with one writer, one reader and **no pruner**, and
 * its creating migration said so twice — *"nothing in this application prunes
 * this table"* — as part of the argument for the hourly rollup. The rollup is
 * right and it closes the **accidental** case absolutely: 4968's mis-installed
 * tenant emits one refused beacon per pageview and lands twenty-four rows a day
 * however busy their site is. ⛔ **The migration is equally plain that it closes
 * nothing else**: *"`origin` is a header the client writes, so a caller varying
 * it per request still writes a row per request. That is bounded by
 * `PixelRateLimits` per source and by nothing else here."* That bound is 300
 * beacons a minute per source — **432,000 rows a day** from one host, each
 * carrying up to 512 bytes of text a stranger chose, for ever.
 * ⛔ **THAT SENTENCE WAS TRUE WHEN THIS FILE WAS WRITTEN AND ITS FIGURE IS NO
 * LONGER REACHABLE — BOTH KEPT AND DATED** (7710, 2026-08-22).
 * {@see IngestRejects::ORIGINS_PER_HOUR} folds a tenant's distinct origins past
 * fifty an hour into one overflow bucket, so the adversarial case writes about
 * **1,224 rows a day** instead. ⚠️ **What this command exists for is unchanged**:
 * a bounded growth rate is still growth, and *permanence* was always the half a
 * horizon removes.
 *
 * ⚠️ **THIS IS `operator_alerts`' HORIZON (7520–7539) ARRIVING AT THE TABLE
 * THAT ACTUALLY GROWS.** Wave 10 gave that table a year on the strength of one
 * field — *"an append into a permanent store by somebody outside the building"*
 * — and named this table in the same breath as carrying the identical field
 * **per hour rather than per quiet window**. The horizons differ by a factor of
 * four and {@see IngestRejects::RETENTION_DAYS} carries the argument; ⛔ **7525's
 * three-way test is deliberately NOT cited**, because this table fails it: it
 * *is* tenant-owned and it *is* row-level secured, which `operator_alerts` is
 * not.
 *
 * ## ⛔ THE SEVENTH OWNER WALK, AND HERE IT IS LOAD-BEARING RATHER THAN
 * CUSTOMARY
 *
 * `RefreshOauthTokens`' shape for the seventh time — users, each user's
 * businesses through the `owner_lookup` policy, then the tenant set for the
 * work. ⛔ **On the six before this one the walk was how a sweep found its
 * subjects. Here it is what makes the DELETE do anything at all**:
 * `ingest_rejects` is `ENABLE`+`FORCE` row-level secured on `app.business_id`,
 * and the application connects as a non-owner role, so a single range DELETE
 * from a command with no tenant set **matches zero rows and exits 0**. A pruner
 * is the worst possible host for that failure, because *"deleted nothing"* is
 * also what a healthy night looks like — it would have read as a working sweep
 * for as long as anybody cared to look.
 *
 * ⚠️ **`businesses.owner_user_id` IS NOT NULL AND CASCADES**, so this walk
 * reaches every business that exists; there is no orphan set it silently skips.
 * Checked rather than assumed — the same walk over a nullable owner column
 * would leave a subset of tenants unpruned for ever with the output still
 * reading as a clean run.
 *
 * ⚠️ **A FAILED PRUNE IS NOT A FAILED RUN**, `PruneTenantExports`' rule.
 * {@see IngestRejects::prune()} contains and logs its own failure and returns
 * `0`, so one tenant's lock contention cannot cost the other tenants their
 * sweep. ⛔ **The cost of that is stated rather than hidden: this command cannot
 * tell a tenant whose delete failed from a tenant with nothing to delete.** The
 * accounts figure below is what distinguishes *"the walk ran and found nothing"*
 * from *"the walk did not run"*; the per-tenant failure is a `warning` in the
 * log and nowhere else.
 */
#[Signature('pixel:prune-rejects')]
#[Description('Delete pixel ingest-reject buckets past their retention window')]
final class PruneIngestRejects extends Command
{
    public function handle(IngestRejects $rejects): int
    {
        // Never inherit a tenant from whatever ran before this in the process.
        Tenancy::forgetAll();

        $deleted = 0;
        $accounts = 0;

        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use (&$deleted, &$accounts, $rejects): void {
                foreach ($users as $user) {
                    [$swept, $seen] = $this->sweepOwner((int) $user->getKey(), $rejects);

                    $deleted += $swept;
                    $accounts += $seen;
                }
            });

        // The PostgreSQL session variable outlives this process's connection
        // under any pooler, and a worker inheriting it would start as whichever
        // tenant this loop happened to touch last.
        Tenancy::forgetAll();

        $this->info($deleted === 0
            ? 'No reject buckets older than '.$rejects->retentionDays()." days across {$accounts} "
                .str('account')->plural($accounts).'.'
            : "Pruned {$deleted} reject ".str('bucket')->plural($deleted)
                .' older than '.$rejects->retentionDays()." days across {$accounts} "
                .str('account')->plural($accounts).'.');

        return self::SUCCESS;
    }

    /**
     * Every business this user owns, through the `owner_lookup` policy.
     *
     * @return array{0: int, 1: int} buckets deleted, and accounts visited
     */
    private function sweepOwner(int $userId, IngestRejects $rejects): array
    {
        Tenancy::setUser($userId);

        // withoutGlobalScopes because the scope calls Tenancy::idOrFail() and no
        // tenant is established yet — the circularity `ResolveTenant` documents.
        // The database still restricts this to businesses owned by the user just
        // set, so it cannot widen beyond one person's own.
        $businessIds = Business::withoutGlobalScopes()
            ->where('owner_user_id', $userId)
            ->pluck('id');

        $deleted = 0;

        foreach ($businessIds as $businessId) {
            $deleted += Tenancy::actingAs(
                (int) $businessId,
                fn (): int => $rejects->prune($rejects->retentionDays()),
            );
        }

        return [$deleted, $businessIds->count()];
    }
}
