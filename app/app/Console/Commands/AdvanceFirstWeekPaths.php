<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\AdvanceFirstWeekPathJob;
use App\Models\Business;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * One daily tick for every tenant's First 7-Day Results Path (`28` §3.2).
 *
 * THE ENUMERATION FOLLOWS `reviews:reanalyse` AND `reviews:reinvite`, AND FOR
 * THEIR REASONS. This runs outside any tenant, `businesses` is
 * FORCE ROW LEVEL SECURITY on a policy keyed to the session tenant, so
 * `Business::all()` returns nothing — and dropping a global scope does not
 * help, because the policy is in the database. Reaching each business through
 * its owner via `owner_lookup` grants this sweep no privilege a logged-in
 * owner does not already have. Read `ReinviteDeferredReviews`' docblock before
 * changing this one.
 *
 * ⚠️ **DISPATCHED FOR EVERY BUSINESS, NOT ONLY THOSE WITH A `first_week_runs`
 * ROW.** Filtering first would need a second `withoutGlobalScopes()` query on a
 * tenant-owned table, widening the allowlist for no real saving:
 * `AdvanceFirstWeekPathJob` already no-ops in under a millisecond for a tenant
 * whose wizard has never completed, and that is the same trade
 * `ReinviteDeferredReviews` makes for a tenant with nothing deferred.
 *
 * `withoutOverlapping()` because the enumeration walks every user, the same
 * cost as the sweeps above.
 */
#[Signature('trust:advance-first-week-paths')]
#[Description("Advance every tenant's First 7-Day Results Path by one tick")]
final class AdvanceFirstWeekPaths extends Command
{
    public function handle(): int
    {
        $dispatched = 0;

        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use (&$dispatched): void {
                foreach ($users as $user) {
                    $dispatched += $this->dispatchForOwner((int) $user->getKey());
                }
            });

        // Never leave a security context established after a console command —
        // the PostgreSQL session variable outlives this process's connection
        // under any pooler, and a worker inheriting it would start as whichever
        // tenant the loop happened to touch last.
        Tenancy::forgetAll();

        $this->info($dispatched === 0
            ? 'No businesses to advance.'
            : "Dispatched {$dispatched} first-week-path ".str('tick')->plural($dispatched).'.');

        return self::SUCCESS;
    }

    private function dispatchForOwner(int $userId): int
    {
        Tenancy::setUser($userId);

        // withoutGlobalScopes because the scope calls Tenancy::idOrFail() and
        // no tenant is established yet — the same circularity ResolveTenant
        // documents. The database still restricts this to businesses owned by
        // the user just set, so it cannot widen beyond one person's own.
        $businessIds = Business::withoutGlobalScopes()
            ->where('owner_user_id', $userId)
            ->pluck('id');

        foreach ($businessIds as $businessId) {
            AdvanceFirstWeekPathJob::dispatch((int) $businessId);
        }

        return $businessIds->count();
    }
}
