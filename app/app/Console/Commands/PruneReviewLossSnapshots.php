<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\User;
use App\Services\Visibility\GoogleRatingSnapshotRetention;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delete `google_rating_snapshots` rows past the period an operator has
 * stated in Ops — wave 38 lane D, `App\Console\Commands\PruneAutomationRuns`'
 * exact shape, simplified for a table with no survivor rule to carry.
 *
 * ⛔ **ON A DEPLOYMENT WHERE NOBODY HAS STATED A PERIOD, THIS COMMAND DELETES
 * NOTHING AND SAYS SO.** That is every deployment today. It is scheduled
 * anyway, on the same precedent `PruneAutomationRuns` and `PruneFetchAttempts`
 * both cite: a sweep that only starts being scheduled on the day a period is
 * set is a second thing to remember on that day.
 *
 * ## The eighth owner walk
 *
 * `RefreshOauthTokens`' shape — users, each user's businesses through the
 * `owner_lookup` policy, then the tenant set for the work. `google_rating_snapshots`
 * is `ENABLE`+`FORCE` row-level secured on `app.business_id`, so a single
 * `delete from google_rating_snapshots where captured_at < ?` issued with no
 * tenant established matches zero rows and exits 0.
 *
 * ⚠️ **A FAILED PRUNE IS NOT A FAILED RUN** — `PruneAutomationRuns`' rule: one
 * tenant's lock contention must not cost every account after it in the walk
 * its sweep, so a throw from {@see GoogleRatingSnapshotRetention::prune()} is
 * caught, logged and counted here rather than allowed to abort the walk.
 */
#[Signature('review-loss:prune-snapshots')]
#[Description('Delete google_rating_snapshots rows past the period stated in Ops')]
final class PruneReviewLossSnapshots extends Command
{
    public function handle(GoogleRatingSnapshotRetention $retention): int
    {
        // Never inherit a tenant from whatever ran before this in the process.
        Tenancy::forgetAll();

        $days = $retention->retentionDays();

        if ($days === null) {
            $this->line(
                'No retention period is set for google_rating_snapshots (key: '
                .GoogleRatingSnapshotRetention::RETENTION_KEY.'), so nothing was deleted. '
                .'Set it in Ops to start pruning.'
            );

            return self::SUCCESS;
        }

        $deleted = 0;
        $accounts = 0;
        $refused = 0;

        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use (&$deleted, &$accounts, &$refused, $retention): void {
                foreach ($users as $user) {
                    [$swept, $seen, $failed] = $this->sweepOwner((int) $user->getKey(), $retention);

                    $deleted += $swept;
                    $accounts += $seen;
                    $refused += $failed;
                }
            });

        Tenancy::forgetAll();

        $this->info($deleted === 0
            ? "No Google rating snapshots older than {$days} days across {$accounts} "
                .str('account')->plural($accounts).'.'
            : "Pruned {$deleted} Google rating ".str('snapshot')->plural($deleted)
                ." older than {$days} days across {$accounts} ".str('account')->plural($accounts).'.');

        if ($refused > 0) {
            $this->warn(
                $refused.' '.str('account')->plural($refused)
                .' could not be swept — see the log for the exception. The rows are '
                .'left exactly as they were, so tomorrow\'s run tries again.'
            );
        }

        return self::SUCCESS;
    }

    /**
     * Every business this user owns, through the `owner_lookup` policy.
     *
     * @return array{0: int, 1: int, 2: int} rows deleted, accounts visited, accounts refused
     */
    private function sweepOwner(int $userId, GoogleRatingSnapshotRetention $retention): array
    {
        Tenancy::setUser($userId);

        // withoutGlobalScopes because the scope calls Tenancy::idOrFail() and
        // no tenant is established yet — the circularity ResolveTenant
        // documents. The database still restricts this to businesses owned by
        // the user just set, so it cannot widen beyond one person's own.
        $businessIds = Business::withoutGlobalScopes()
            ->where('owner_user_id', $userId)
            ->pluck('id');

        $deleted = 0;
        $refused = 0;

        foreach ($businessIds as $businessId) {
            try {
                $deleted += Tenancy::actingAs(
                    (int) $businessId,
                    fn (): int => $retention->prune(),
                );
            } catch (Throwable $e) {
                $refused++;

                Log::warning('google rating snapshots could not be pruned', [
                    'business_id' => $businessId,
                    'exception' => $e::class,
                ]);
            }
        }

        return [$deleted, $businessIds->count(), $refused];
    }
}
