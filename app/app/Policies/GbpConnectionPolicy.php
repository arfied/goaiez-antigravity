<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ImpersonationCapability;
use App\Models\GbpConnection;
use App\Models\User;

/**
 * Who may hand a third party read **and write** access to the business's own
 * Google listing, and who may take it away again.
 *
 * ⛔ **THIS IS 6455, AND THE ACT IS LARGER THAN THE SCREEN IT SITS ON.**
 * `GbpConnections::begin()` sends an owner to a consent screen that grants
 * Zernio `business.manage` on a Google Business Profile — reading reviews and
 * *posting* under the business's name, in one consent flow whose scopes cannot
 * be requested separately (`SUBPROCESSOR-INVENTORY` §2). Until this file existed
 * `Account\Connections` asked nothing at all about the actor: connect, check and
 * disconnect were open to every role that could reach `/account`.
 *
 * ⚠️ **IT IS A SECOND POLICY RATHER THAN A SECOND CALL SITE ON
 * {@see OauthConnectionPolicy}, AND THAT IS NOT A NAMING ACCIDENT.**
 * `gbp_connections` is a different table with a different model, and Laravel
 * resolves a policy from the model it is asked about — so a `Gate::authorize()`
 * against a `GbpConnection` reaches *this* file whatever `OauthConnectionPolicy`
 * says. Sharing one policy would have meant `Gate::policy()` wiring that exists
 * nowhere in this application (`AuthorizationTest` pins that assumption), for
 * two stores whose only common property is the sentence below.
 *
 * ⚠️ **THE PREDICATE IS SHARED, HOWEVER, AND DELIBERATELY.**
 * `UserRole::canManageConnections()` is the same one `OauthConnectionPolicy`
 * asks, and 2965 is the argument for keeping it apart from
 * `canConfigureAutomation()`: it excludes a **manager** as well as `staff`,
 * because a credential that acts as the business is not the same class of act as
 * a standing configuration choice. A Google grant is the strongest instance of
 * that class in this codebase — it writes to a surface the public reads.
 *
 * ## Three abilities, two answers — the question 6455 left open
 *
 *   `create`  Connect. Mints the grant. `canManageConnections()`.
 *   `delete`  Disconnect. Revokes it at the vendor. `canManageConnections()`.
 *   `check`   Ask the provider whether the grant still works. Everyone.
 *
 * **`create` and `delete` share an answer because disconnecting is as
 * consequential as connecting** — {@see OauthConnectionPolicy::delete()} says so
 * of its own store and it is truer here: `GbpConnections::disconnect()` calls
 * the vendor first and writes second, so the grant is genuinely gone and getting
 * it back means the owner consenting again at Google. Handing that to a role
 * that cannot then restore it would create a state only somebody else can leave.
 *
 * ⛔ **`check` IS A CUSTOM ABILITY RATHER THAN `update`, AND IT IS OPEN ON
 * PURPOSE.** `refreshHealth()` does write — `last_checked_at`, `last_error`,
 * and a transition to `disconnected` when the provider reports the grant dead —
 * but what it writes is *the provider's answer*, not a decision of the actor's.
 * Three things carry it:
 *
 *   - It changes nothing about who may reach the listing. Nothing is minted and
 *     nothing is revoked; a grant that comes back dead was already dead.
 *   - **The same transition already happens unattended.**
 *     `SyncGoogleReviewsJob` and `PostReplyJob` both call `refreshHealth()` with
 *     a `system:` actor, so pressing the button only makes a discovery the next
 *     scheduled sync would make anyway. A gate here would guard a door the
 *     scheduler already walks through.
 *   - The person who works the review inbox is very often not the person who
 *     owns the relationship, and *"why have no reviews arrived since Tuesday"*
 *     is their question. Refusing them the answer turns a two-second look into a
 *     message to the owner, which is CLAUDE.md's *less support surface* rule
 *     read backwards.
 *
 * ⚠️ **THERE IS NO `update`, AND ITS ABSENCE IS THE DECISION.** The only write
 * on this model outside connect/disconnect is `refreshHealth()`, which is
 * `check`. An `update()` method here would be a rule nobody asks, in the file
 * written to close a slice about rules nobody asks (6442) — 272's shape wearing
 * an authorization layer. `restore` and `forceDelete` went for the same reason:
 * `gbp_connections` has no soft deletes, so neither could ever be asked.
 *
 * ## What this file deliberately does not check
 *
 * **The tenant.** {@see AutopilotSettingsPolicy} states the rule and it holds
 * here: a policy answers *"may this role do this?"*; the global scope on
 * `GbpConnection` and `gbp_connections`' `ENABLE`+`FORCE` row-level security
 * answer *"is this row yours?"*. Conflating them makes the weaker one look like
 * the boundary. Every row reaching this class arrived through
 * `GbpConnections::find()`, which is scoped.
 *
 * **Impersonation.** A support session acts *as the owner*, so
 * `canManageConnections()` is asked of the owner's role and answers yes.
 * {@see ImpersonationCapability::ManageConnections} is the layer that
 * refuses it, and it lives at the service rather than here for the reason every
 * other capability does — a policy is asked by a screen, and a service is asked
 * by everything that reaches it.
 */
final class GbpConnectionPolicy
{
    /**
     * Whether the connections screen has anything to show.
     *
     * True for everyone signed in to the business. No credential reaches this
     * model at all — Zernio holds the grant and we hold an opaque account
     * reference — so what a viewer learns is *"Google: connected"*, which is
     * the same thing {@see OauthConnectionPolicy::viewAny()} permits and for the
     * same reason.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, GbpConnection $connection): bool
    {
        return true;
    }

    /**
     * Ask the provider whether this grant still works, and record the answer.
     *
     * See the class docblock: open on purpose, because the write is the
     * provider's answer rather than the actor's decision, and two background
     * jobs make the same call unattended.
     */
    public function check(User $user, GbpConnection $connection): bool
    {
        return true;
    }

    /**
     * Start a connection — the act that mints `business.manage` on a customer's
     * Google listing.
     */
    public function create(User $user): bool
    {
        return $user->role->canManageConnections();
    }

    /**
     * End it. As consequential as starting it, and harder to undo.
     */
    public function delete(User $user, GbpConnection $connection): bool
    {
        return $user->role->canManageConnections();
    }
}
