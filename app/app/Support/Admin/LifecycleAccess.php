<?php

declare(strict_types=1);

namespace App\Support\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Who may act on a tenant's lifecycle (`28` §9.5).
 *
 * ⚠️ **TWO ABILITIES, NOT ONE, AND NOT A THIRD ROUTE GATE.** These sit *inside*
 * the support console rather than in front of it: `28` §9.5 puts the actions on
 * Account 360, which is already behind `SupportAccess::GATE`, and §9.2's whole
 * navigation is role-filtered. So the screen admits `support_agent` and above
 * as it always did, and each button asks its own ability — decision 575's
 * pattern, where the nav names no role and each item declares what it needs.
 *
 * ⚠️ **AND THEY ARE TWO BECAUSE `SupportAccess` IS WIDER THAN EITHER.** That
 * gate is *"may open a session of some strength"*, which admits `support_agent`
 * and `ops_admin`. Reusing it here would let a first-week agent stop a paying
 * customer's product, and reusing `AdminAccess` (which is `super_admin` alone)
 * would refuse the `ops_admin` §9.5 names by name. Neither existing gate
 * answers this question, which is why there is a new file rather than a wider
 * one — `AdminAccess`'s own docblock records what happens when a gate is
 * widened to cover a second meaning.
 *
 * The predicates themselves live on {@see UserRole}, with the
 * derivation written down, because that is where every other capability
 * question in this application is answered.
 */
final class LifecycleAccess
{
    /**
     * Suspend a tenant, or lift a suspension. `super_admin` / `ops_admin`,
     * quoted from `28` §9.5.
     */
    public const SUSPEND = 'suspend-tenants';

    /**
     * Pause a tenant's account on their behalf, or start it again for them.
     * Wider than SUSPEND, because a pause is reversible by the owner and a
     * suspension is not.
     */
    public const PAUSE = 'pause-tenants-on-behalf';

    public static function register(): void
    {
        Gate::define(self::SUSPEND, static fn (User $user): bool => $user->role->canSuspendTenants());

        Gate::define(self::PAUSE, static fn (User $user): bool => $user->role->canPauseTenantsOnBehalf());
    }
}
