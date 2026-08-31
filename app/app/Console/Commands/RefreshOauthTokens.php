<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RefreshOauthTokensJob;
use App\Models\Business;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Fan the token refresh out across every business.
 *
 * THE ENUMERATION IS THE INTERESTING PART, and it is not the obvious one.
 *
 * `Business::all()` returns nothing here, and correctly so. The runtime role is
 * a non-owner and `businesses` is FORCE ROW LEVEL SECURITY with a policy keyed
 * on the session tenant, so listing every business is exactly the cross-tenant
 * read the boundary exists to prevent. Removing the global scope does not help:
 * the policy lives in the database.
 *
 * Three ways out, and only one of them is acceptable:
 *
 *   1. run the sweep on the owner connection — puts a runtime code path on the
 *      role RLS does not constrain, which is the single failure mode
 *      db-bootstrap.sql exists to prevent
 *   2. add a policy letting the app role read every business — weakens the
 *      tenant root's isolation permanently, for the sake of a scheduler
 *   3. reach each business through its owner, which is what this does
 *
 * `users` carries no tenant and is on the ArchitectureTest allowlist, so it can
 * be iterated. `businesses.owner_user_id` is NOT NULL and foreign-keyed, so
 * every business has exactly one owner and none is missed. The per-user lookup
 * is the existing `owner_lookup` policy — the same deliberate, SELECT-only
 * affordance ResolveTenant uses to answer "which business is yours", added in
 * its own migration with its own reasoning.
 *
 * The sweep therefore gains no privilege a logged-in owner does not already
 * have. That is the property worth preserving, and it is why this is a command
 * that dispatches jobs rather than a job that queries widely.
 */
#[Signature('oauth:refresh-tokens {--business= : Refresh a single business by id}')]
#[Description('Renew OAuth access tokens that are close to expiry')]
final class RefreshOauthTokens extends Command
{
    public function handle(): int
    {
        $only = $this->option('business');

        if (is_string($only) && $only !== '') {
            RefreshOauthTokensJob::dispatch((int) $only);

            $this->info('Queued a token refresh for business '.$only.'.');

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
        // user the loop happened to touch last.
        Tenancy::forgetAll();

        $this->info('Queued token refreshes for '.$dispatched.' businesses.');

        return self::SUCCESS;
    }

    /**
     * Dispatch for every business this user owns, through the owner_lookup policy.
     */
    private function dispatchForOwner(int $userId): int
    {
        Tenancy::setUser($userId);

        // withoutGlobalScopes because the scope calls Tenancy::idOrFail() and no
        // tenant is established yet — the same circularity ResolveTenant
        // documents. The database still restricts this to businesses owned by
        // the user just set, so it cannot widen beyond one person's own.
        $businessIds = Business::withoutGlobalScopes()
            ->where('owner_user_id', $userId)
            ->pluck('id');

        foreach ($businessIds as $businessId) {
            RefreshOauthTokensJob::dispatch((int) $businessId);
        }

        return $businessIds->count();
    }
}
