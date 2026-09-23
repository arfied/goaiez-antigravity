<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\User;
use App\Services\Messaging\SendingHealth;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Delete per-tenant sending buckets past {@see app(SendingHealth::class)->retentionDays()}
 * (7760-7779, closing 2199).
 *
 * ## ⛔ WHAT THIS CLOSES, AND WHY IT IS THE SAME SHAPE AS THE TABLE'S OWN
 * FOUNDING DEFECT
 *
 * `SendingHealth::prune()` was written on 2026-08-11, tested on the same day,
 * and **had no caller anywhere in `app/` or `routes/` from then until this
 * command** — 2199 recorded it as owed the day it shipped, 7539 restated it
 * eleven days later, and nothing moved either time. `CLAUDE.md`'s first
 * recurring failure shape, wearing a DELETE, on the table whose *previous*
 * instance of that shape (2496-2499, no writer for eleven days) is the reason
 * the file above carries three paragraphs about it.
 *
 * ⚠️ **WHAT GREW MEANWHILE IS HOURLY BUCKETS TIMES CHANNEL TIMES EVERY
 * TENANT** — up to forty-eight rows a day each, for ever — and
 * {@see SendingHealth::rates()} reads this table with `window_start >= …` on the
 * path that decides whether a message may be sent at all. ⛔ **The size is not
 * the interesting half.** A rolling twenty-four-hour `SUM` over the index on
 * `(business_id, channel, window_start)` does not care how much expired history
 * sits behind it; what a horizon removes is a table nobody ever cleans, on a
 * platform whose own creating migration promised in §3.5 that it would be
 * prunable.
 *
 * ## ⛔ THE EIGHTH OWNER WALK, AND HERE IT IS LOAD-BEARING RATHER THAN
 * CUSTOMARY
 *
 * `RefreshOauthTokens`' shape — users, each user's businesses through the
 * `owner_lookup` policy, then the tenant set for the work. ⛔ **It is
 * `PruneIngestRejects`' reason and not the other six's** (7626):
 * `sending_health_windows` is `ENABLE`+`FORCE` row-level secured on
 * `app.business_id` and the application connects as a non-owner role, so a
 * single `delete from sending_health_windows where window_start < ?` issued
 * from a command with no tenant established **matches zero rows and exits 0**.
 * A pruner is the worst possible host for that failure, because *"deleted
 * nothing"* is also what a healthy night looks like.
 *
 * ⛔ **AND ON THIS TABLE THE WRONG VERSION HAD ALREADY BEEN WRITTEN AND
 * DOCUMENTED AS CORRECT.** {@see SendingHealth::prune()}'s own docblock said the
 * delete *"deliberately steps outside the tenant scope … the one operation on
 * this table that may"*, which describes exactly the statement the database
 * refuses. **A method with no caller cannot be disproved by running it**, and
 * the two tests that did call it were both inside `Tenancy::actingAs()`, where
 * it works perfectly.
 *
 * ⚠️ **`businesses.owner_user_id` IS NOT NULL AND CASCADES**, checked in the
 * creating migration rather than assumed, so this walk reaches every business
 * that exists and there is no orphan set it silently skips (7627). The same
 * walk over a nullable owner column would leave a subset of tenants unpruned
 * for ever with the output still reading as a clean run.
 *
 * ⚠️ **ONE DELETE PATH ALREADY EXISTED AND IT IS NOT CODE.** `business_id` is
 * `constrained()->cascadeOnDelete()`, so erasing a tenant carries their buckets
 * out with them. This table has therefore never been unbounded in the *number
 * of tenants*; it has always been unbounded in *time*, which is the half this
 * closes.
 *
 * ## ⚠️ A FAILED PRUNE IS NOT A FAILED RUN (7630)
 *
 * {@see SendingHealth::prune()} contains and logs its own failure and returns
 * `0`, so one tenant's lock contention cannot cost the other tenants their
 * sweep. ⛔ **The cost is stated rather than hidden: this command cannot tell a
 * tenant whose delete failed from a tenant with nothing to delete.** The
 * accounts figure below is what distinguishes *"the walk ran and found
 * nothing"* from *"the walk did not run"*; the per-tenant failure is a
 * `warning` in the log and nowhere else.
 */
#[Signature('messaging:prune-sending-health')]
#[Description('Delete per-tenant sending health buckets past their retention window')]
final class PruneSendingHealth extends Command
{
    public function handle(SendingHealth $health): int
    {
        // Never inherit a tenant from whatever ran before this in the process.
        Tenancy::forgetAll();

        $deleted = 0;
        $accounts = 0;

        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use (&$deleted, &$accounts, $health): void {
                foreach ($users as $user) {
                    [$swept, $seen] = $this->sweepOwner((int) $user->getKey(), $health);

                    $deleted += $swept;
                    $accounts += $seen;
                }
            });

        // The PostgreSQL session variable outlives this process's connection
        // under any pooler, and a worker inheriting it would start as whichever
        // tenant this loop happened to touch last.
        Tenancy::forgetAll();

        $this->info($deleted === 0
            ? 'No sending health buckets older than '.app(SendingHealth::class)->retentionDays()." days across {$accounts} "
                .str('account')->plural($accounts).'.'
            : "Pruned {$deleted} sending health ".str('bucket')->plural($deleted)
                .' older than '.app(SendingHealth::class)->retentionDays()." days across {$accounts} "
                .str('account')->plural($accounts).'.');

        return self::SUCCESS;
    }

    /**
     * Every business this user owns, through the `owner_lookup` policy.
     *
     * @return array{0: int, 1: int} buckets deleted, and accounts visited
     */
    private function sweepOwner(int $userId, SendingHealth $health): array
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
                fn (): int => $health->prune(app(SendingHealth::class)->retentionDays()),
            );
        }

        return [$deleted, $businessIds->count()];
    }
}
