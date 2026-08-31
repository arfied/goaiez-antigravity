<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\User;
use App\Services\Automation\AutomationRunRetention;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delete finished `automation_runs` rows past the period an operator has
 * stated, keeping the newest terminal run of every location/automation/reply —
 * decisions 10184, 10185, 10260, closing what wave 34 refused to build.
 *
 * ⛔ **ON A DEPLOYMENT WHERE NOBODY HAS STATED A PERIOD, THIS COMMAND DELETES
 * NOTHING AND SAYS SO.** That is every deployment today. It is scheduled anyway,
 * on `PruneStoredObjects`'s precedent: a sweep that only starts being scheduled
 * on the day a period is set is a second thing to remember on that day.
 *
 * ⛔ **THE SURVIVOR RULE SHIPS IN THIS SAME COMMAND, NEVER AFTER IT.** Wave 34
 * refused the pruner precisely because a horizon with no survivor rule turns
 * *"we have never read your Google listing"* into a sentence said to a tenant
 * read every fifteen minutes for a year — see
 * {@see AutomationRunRetention}'s own docblock for the argument in full, and
 * `tests/Feature/Architecture/AutomationRunSurvivorTest.php` for the behavioural
 * proof that `Services\Visibility\VisibilitySyncHistory`'s three absence readers
 * survive a prune unchanged.
 *
 * ## The seventh owner walk
 *
 * `RefreshOauthTokens`' shape — users, each user's businesses through the
 * `owner_lookup` policy, then the tenant set for the work — for
 * `PruneSendingHealth`'s exact reason: `automation_runs` is `ENABLE`+`FORCE` row-
 * level secured on `app.business_id`, so a single
 * `delete from automation_runs where finished_at < ?` issued with no tenant
 * established matches zero rows and exits 0. A pruner is the worst possible host
 * for that failure, because *"deleted nothing"* is also what a healthy night
 * looks like — which is why every test in
 * `tests/Feature/Automation/AutomationRunRetentionTest.php` that proves the
 * delete works drives it through this command, rather than through
 * {@see AutomationRunRetention::prune()} called directly inside
 * `Tenancy::actingAs()`, where the tenant boundary cannot be seen failing at
 * all.
 *
 * ⚠️ **A FAILED PRUNE IS NOT A FAILED RUN** — `SendingHealth::prune()`'s rule
 * (7630). One tenant's lock contention must not cost every account after it in
 * the walk its sweep, so a throw from {@see AutomationRunRetention::prune()} is
 * caught, logged and counted here rather than allowed to abort the walk.
 */
#[Signature('automation:prune-runs')]
#[Description('Delete finished automation_runs rows past the period stated in Ops, keeping every survivor')]
final class PruneAutomationRuns extends Command
{
    public function handle(AutomationRunRetention $retention): int
    {
        // Never inherit a tenant from whatever ran before this in the process.
        Tenancy::forgetAll();

        $days = $retention->retentionDays();

        if ($days === null) {
            $this->line(
                'No retention period is set for automation_runs (key: '
                .AutomationRunRetention::RETENTION_KEY.'), so nothing was deleted. '
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

        // The PostgreSQL session variable outlives this process's connection
        // under any pooler, and a worker inheriting it would start as whichever
        // tenant this loop happened to touch last.
        Tenancy::forgetAll();

        $this->info($deleted === 0
            ? "No automation runs older than {$days} days across {$accounts} "
                .str('account')->plural($accounts).'.'
            : "Pruned {$deleted} automation ".str('run')->plural($deleted)
                ." older than {$days} days across {$accounts} ".str('account')->plural($accounts).'.');

        if ($refused > 0) {
            // ⚠️ AFTER the line above rather than instead of it — `PruneStoredObjects`'
            // rule: "nothing was deleted" and "something could not be deleted"
            // are separate facts, and collapsing them hides the one an operator
            // would act on.
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
     * @return array{0: int, 1: int, 2: int} runs deleted, accounts visited, accounts refused
     */
    private function sweepOwner(int $userId, AutomationRunRetention $retention): array
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
        $refused = 0;

        foreach ($businessIds as $businessId) {
            try {
                $deleted += Tenancy::actingAs(
                    (int) $businessId,
                    fn (): int => $retention->prune(),
                );
            } catch (Throwable $e) {
                $refused++;

                Log::warning('automation runs could not be pruned', [
                    'business_id' => $businessId,
                    'exception' => $e::class,
                ]);
            }
        }

        return [$deleted, $businessIds->count(), $refused];
    }
}
