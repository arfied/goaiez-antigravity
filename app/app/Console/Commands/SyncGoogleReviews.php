<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Reviews\SyncGoogleReviewsJob;
use App\Models\Business;
use App\Models\User;
use App\Services\Gbp\GbpConnections;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Fan Google review sync across every usable GbpConnection.
 *
 * THE ENUMERATION IS `oauth:refresh-tokens`', VIA `gsc:sync`, AND FOR THEIR
 * REASONS. This runs outside any tenant, `businesses` is FORCE ROW LEVEL
 * SECURITY on a policy keyed to the session tenant, so `Business::all()`
 * returns nothing and dropping a global scope does not help — the policy is in
 * the database. Reaching each business through its owner via `owner_lookup` is
 * the only one of the three ways out that grants the sweep no privilege a
 * logged-in owner does not already have. **Read `RefreshOauthTokens`' docblock
 * before changing this one**; the argument is set out there in full and is not
 * repeated. This file is the sixth copy of it and is named in the
 * `withoutGlobalScopes` lint with that reason.
 *
 * ⚠️ **IT ENUMERATES USABLE CONNECTIONS, NOT LOCATIONS**, and it asks
 * `GbpConnections::usableLocationIds()` rather than `GbpConnection::` — H's
 * chokepoint lint (1352, 1664). A tenant with forty locations and one Google
 * binding should produce one job, not forty handoffs; dispatching per location
 * and letting `canExecute()` refuse would be correct and would write
 * thirty-nine `handed_off` run rows every fifteen minutes forever, which is
 * decision 380's feed made worse by exhaustiveness.
 */
#[Signature('gbp:sync {--business= : Sync a single business by id}')]
#[Description('Pull Google reviews for every connected location')]
final class SyncGoogleReviews extends Command
{
    public function handle(): int
    {
        if (SyncGoogleReviewsJob::killSwitchThrownFor('reviews.google_sync')) {
            $this->info('Google review sync is switched off.');

            return self::SUCCESS;
        }

        $only = $this->option('business');

        if (is_string($only) && $only !== '') {
            $dispatched = $this->dispatchForBusiness((int) $only);

            Tenancy::forgetAll();

            $this->info('Queued '.$dispatched.' Google review syncs for business '.$only.'.');

            return self::SUCCESS;
        }

        $dispatched = 0;

        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use (&$dispatched): void {
                foreach ($users as $user) {
                    $dispatched += $this->dispatchForOwner((int) $user->getKey());
                }
            });

        Tenancy::forgetAll();

        $this->info('Queued '.$dispatched.' Google review syncs.');

        return self::SUCCESS;
    }

    private function dispatchForOwner(int $userId): int
    {
        Tenancy::setUser($userId);

        // withoutGlobalScopes because the scope calls Tenancy::idOrFail() and no
        // tenant is established yet — the circularity ResolveTenant documents.
        // The database still restricts this to businesses owned by the user just
        // set, so it cannot widen beyond one person's own.
        $businessIds = Business::withoutGlobalScopes()
            ->where('owner_user_id', $userId)
            ->pluck('id');

        $dispatched = 0;

        foreach ($businessIds as $businessId) {
            $dispatched += $this->dispatchForBusiness((int) $businessId);
        }

        return $dispatched;
    }

    private function dispatchForBusiness(int $businessId): int
    {
        return (int) Tenancy::actingAs($businessId, function () use ($businessId): int {
            $locationIds = app(GbpConnections::class)->usableLocationIds();

            foreach ($locationIds as $locationId) {
                SyncGoogleReviewsJob::dispatch($businessId, (int) $locationId);
            }

            return $locationIds->count();
        });
    }
}
