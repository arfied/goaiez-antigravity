<?php

declare(strict_types=1);

namespace App\Support\Admin;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Support\CreditGrants;
use Illuminate\Support\Facades\Gate;

/**
 * Who may grant a tenant credits from the support console (`28` §9.1, §9.3).
 *
 * A third gate alongside {@see LifecycleAccess}'s two, for the same reason that
 * class gives: `SupportAccess::GATE` is *"may open a session of some
 * strength"*, which admits `ops_admin` for view-only impersonation alone — and
 * reusing it here would let a role nobody named in connection with money move
 * credits into a tenant's ledger. The eligibility predicate lives on
 * {@see UserRole::mayGrantCredits()}, where every other capability
 * question in this application is answered.
 *
 * ⚠️ **THE CEILING IS NOT THIS GATE'S TO ANSWER.** A `Gate` is a yes/no
 * question and an amount is not one of the two, so *how much* a role may grant
 * is asked directly of {@see UserRole::creditGrantCeiling()} by
 * {@see CreditGrants} — the service, not this class,
 * because that is the guard that must hold even when the caller is not a
 * Livewire component sitting behind this Gate. Refusing here only proves the
 * console asks first; the ceiling has to be true on its own.
 *
 * ⛔ **AND IT IS NOW *THREE* CEILINGS, WHICH IS STILL NOT A GATE'S SHAPE** (3426).
 * The ceiling is per product, and a `support_agent`'s email and AI figures are
 * withheld — asking for one raises `App\Exceptions\WithheldGrantCeiling` rather
 * than answering. **That refusal is deliberately not modelled here as a second
 * ability.** A per-product gate would put *"the owner has not ruled a number
 * yet"* into the same vocabulary as *"this role has no business moving money"*,
 * and the two clear at completely different times: the first ends with one
 * sentence from the owner, the second never. This gate keeps answering **may they
 * act on money at all**; which products that reaches is the service's answer and
 * the screen's to show.
 */
final class CreditGrantAccess
{
    public const GATE = 'grant-tenant-credits';

    public static function register(): void
    {
        Gate::define(self::GATE, static fn (User $user): bool => $user->role->mayGrantCredits());
    }
}
