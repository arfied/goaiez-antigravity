<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Who may answer a tenant's support request in the platform's name.
 *
 * ## What this is a claim about, and what it is not
 *
 * ⚠️ **"CANNOT REACH THE SCREEN" AND "MAY NOT PERFORM THE ACT" ARE DIFFERENT
 * FACTS** (2642), and only the second survives somebody adding a second door — a
 * console command, a second screen, an API token. The console queue sits behind
 * `SupportAccess::GATE`, whose population happens to be the same four roles this
 * class admits today; the test drives an **owner** and a **`cs_readonly`**
 * against `answer` through the Gate directly, with no route and no screen, so
 * nothing upstream can be what refuses.
 *
 * ⚠️ **AND IT IS SAID PLAINLY THAT THIS IS NOT NARROWER THAN THE GATE TODAY.**
 * `SupportAccess` admits `strongestImpersonationMode() !== null`, which is
 * `super_admin`, `support_lead`, `ops_admin` and `support_agent` — the same set.
 * Claiming a narrower authority than the code has is 314–316's shape, so the
 * claim made here is the honest one: **a tenant may never answer as us**, on any
 * path, and a role that arrives at this desk later needs a line in this file.
 *
 * ## The tenant boundary is not checked here
 *
 * `SendingPausePolicy`'s rule: a policy answers *"may this role do this?"*, the
 * tenant boundary answers *"is this row yours?"*. `support_tickets` is
 * `BelongsToTenant` and RLS-`FORCE`d, so a ticket can only be resolved inside
 * its own tenancy — `SupportDesk` establishes that with `Tenancy::actingAs()`
 * from the queue row rather than from anything a screen supplied.
 *
 * ## Why the tenant's own side has no ability here
 *
 * Raising a request and replying on your own thread are not authorised by a
 * role — every signed-in person on an account may do both, and the only
 * question that matters is whether the thread is theirs, which is the boundary
 * above. A `create` ability admitting everybody would be 256's vacuous gate.
 */
final class SupportTicketPolicy
{
    /**
     * Read the console queue.
     */
    public function viewAny(User $user): bool
    {
        return $this->isSupportStaff($user->role);
    }

    /**
     * Write a reply that reaches a customer with GO AI EZ's name on it.
     */
    public function answer(User $user): bool
    {
        return $this->isSupportStaff($user->role);
    }

    /**
     * Close somebody else's request.
     *
     * ⚠️ **A SEPARATE ABILITY FROM `answer` RATHER THAN BOTH ON ONE NAME.**
     * They are not the same act: answering continues a conversation, closing
     * ends one the tenant may still consider open. The plausible future change
     * is a narrower set of people who may close than may reply, and two names
     * now makes that one line rather than an unpicking (`SendingPausePolicy`'s
     * `create`/`release` split, for the same reason).
     */
    public function resolve(User $user): bool
    {
        return $this->isSupportStaff($user->role);
    }

    /**
     * The support population — `28` §9.1's roles that act on an account, which
     * is what a reply in our name is.
     *
     * Spelled as a match with no default: a role added to the enum has to be
     * answered here rather than inheriting whatever `false` a fall-through
     * would have given it, and inheriting `true` is the direction that would
     * hand somebody an authority nobody granted.
     */
    private function isSupportStaff(UserRole $role): bool
    {
        return match ($role) {
            UserRole::SuperAdmin, UserRole::SupportLead,
            UserRole::SupportAgent, UserRole::OpsAdmin => true,

            // Platform staff, and not support: `cs_readonly` is read-only by
            // name and `billing_admin` has no support duty in `28` §9.1.
            UserRole::CsReadonly, UserRole::BillingAdmin => false,

            // ⛔ The tenant's own roles. A customer answering as us is the one
            // thing on this desk that must be impossible on every path.
            UserRole::Agency, UserRole::Owner, UserRole::Manager,
            UserRole::Staff, UserRole::None => false,
        };
    }
}
