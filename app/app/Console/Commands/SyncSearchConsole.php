<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Visibility\SyncSearchConsoleJob;
use App\Models\Business;
use App\Models\GscSiteProperty;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Fan the daily Search Console read out across every location that has a
 * property mapped to it.
 *
 * THE ENUMERATION IS `oauth:refresh-tokens`', AND FOR ITS REASONS. This runs
 * outside any tenant, `businesses` is FORCE ROW LEVEL SECURITY on a policy keyed
 * to the session tenant, so `Business::all()` returns nothing and dropping a
 * global scope does not help — the policy is in the database. Reaching each
 * business through its owner via the `owner_lookup` policy is the only one of
 * the three ways out that grants the sweep no privilege a logged-in owner does
 * not already have. **Read that command's docblock before changing this one**;
 * the argument is set out there in full and is not repeated.
 *
 * ⚠️ **IT ENUMERATES MAPPED PROPERTIES, NOT LOCATIONS.** A tenant with forty
 * locations and one connected property should produce one job, not forty
 * handoffs. Dispatching per location and letting `canExecute()` refuse would be
 * correct and would write thirty-nine `handed_off` run rows every morning
 * forever — a ledger nobody can read, which is the state decision 380 describes
 * as the feed being made worse by exhaustiveness.
 *
 * ⚠️ **AND IT READS `GscSiteProperty` DIRECTLY, WHICH THE CHOKEPOINT LINT
 * PERMITS BY NAME.** The service's own reader is per-location and this needs the
 * inverse — every location that has one — which is not a question a per-location
 * accessor can answer. Naming the file in the lint was chosen over adding an
 * `allMapped()` to the service that only a console command calls: decision 755
 * records a method written, tested and deleted before push for exactly that
 * reason.
 */
#[Signature('gsc:sync {--business= : Sync a single business by id}')]
#[Description('Read yesterday\'s Google Search Console performance for every mapped location')]
final class SyncSearchConsole extends Command
{
    public function handle(): int
    {
        // The kill switch is asked once here rather than per job. Each job would
        // refuse individually and correctly — and would open a `skipped` run row
        // per location first, so a switched-off automation would still write a
        // row per location every morning. Decision 823's reasoning, and the base
        // class exposes the static for exactly this.
        if (SyncSearchConsoleJob::killSwitchThrownFor('visibility.search_console_sync')) {
            $this->info('Search Console sync is switched off.');

            return self::SUCCESS;
        }

        $only = $this->option('business');

        if (is_string($only) && $only !== '') {
            $dispatched = $this->dispatchForBusiness((int) $only);

            Tenancy::forgetAll();

            $this->info('Queued '.$dispatched.' Search Console syncs for business '.$only.'.');

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

        // Never leave a security context established after a console command.
        // The PostgreSQL session variable outlives this process's connection
        // under any pooler, and a worker inheriting it would start as whichever
        // tenant the loop happened to touch last.
        Tenancy::forgetAll();

        $this->info('Queued '.$dispatched.' Search Console syncs.');

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
            $locationIds = GscSiteProperty::query()
                ->orderBy('id')
                ->pluck('location_id');

            foreach ($locationIds as $locationId) {
                SyncSearchConsoleJob::dispatch($businessId, (int) $locationId);
            }

            return $locationIds->count();
        });
    }
}
