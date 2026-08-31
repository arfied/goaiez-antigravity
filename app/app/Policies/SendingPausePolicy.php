<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\SendingPause;
use App\Models\User;

/**
 * Who may stop a tenant sending, and — the question that actually needed
 * answering — who may start them again.
 *
 * ⚠️ **THIS IS THE POLICY QUESTION 2478 SAID A SCREEN WOULD HAVE TO OPEN.** It
 * recorded that `SendingGuard::history()` had no caller, that no admin screen
 * existed to extend, and that a new one *"is its own slice with its own policy
 * question (a tenant must not be able to clear their own containment, which is
 * `SendingHalts`' argument and still holds)"*. Here is that answer, written down
 * once rather than as a role comparison at three call sites.
 *
 * ## A tenant may never release their own containment
 *
 * 2101 is why: attested lists go out over the **GOAIEZ** 10DLC brand from **our
 * own** number pool, so *"the tenant carries the legal basis while the platform
 * carries the carrier reputation"* — across every tenant at once. A containment
 * the contained party can lift is not a containment, and the party generating
 * the complaints is precisely the party with a reason to lift it. So every
 * ability here asks {@see UserRole::canAdministerPlatform()}, which
 * excludes `owner`, `manager`, `staff` and `agency` outright.
 *
 * ⚠️ **AND THAT IS A REAL, FALSIFIABLE CLAIM RATHER THAN A RESTATEMENT OF THE
 * ROUTE GATE**, which is worth saying because the two look alike. `/admin` is
 * behind `AdminAccess::GATE`, so an owner cannot reach the screen — but "cannot
 * reach the screen" and "may not perform the act" are different facts, and only
 * the second survives somebody adding a second door. A test drives an owner
 * against `release` directly, with no screen and no route involved.
 *
 * ## Why this is not widened to `canSuspendTenant()`
 *
 * The tempting reading is that stopping one tenant's sending is `28` §9.5's
 * operations tooling, which `ops_admin` has and `super_admin` shares. It is not
 * taken, for the reason `UserRole::canAdministerPlatform()` gives itself:
 * *"widening it is a per-screen decision, made on the screen."* The screen this
 * policy serves also carries the **platform-wide** halt, whose blast radius is
 * every tenant at once, and it sits behind `AdminAccess::GATE` — so an
 * `ops_admin` ability here would be one no role can currently exercise, which is
 * 256's vacuous gate wearing an authorization hat. When somebody wants
 * `ops_admin` on this screen, that is a decision with a route change beside it.
 *
 * ## The tenant boundary is not checked here, deliberately
 *
 * `AutopilotSettingsPolicy`'s rule, and it holds harder on this model: a policy
 * answers *"may this role do this?"*, the tenant boundary answers *"is this row
 * yours?"*, and conflating them makes this class read as the boundary and
 * quietly become the place people trust instead of the scope. `sending_pauses`
 * is `BelongsToTenant` and RLS-`FORCE`d, so a `SendingPause` instance can only
 * be resolved inside its own tenant — by the time one reaches this class the
 * boundary has already held. The admin screen establishes that tenant
 * explicitly with `Tenancy::actingAs()`.
 */
final class SendingPausePolicy
{
    /**
     * Read the incident series — 2476's *"how often has this tenant tripped, and
     * at what rate each time"*.
     *
     * Not viewable by the tenant either, and that is a smaller claim than it
     * looks: the rows carry an operator's free-text note and `tripped_by`, which
     * are our record of a compliance decision rather than the tenant's data
     * about themselves.
     */
    public function viewAny(User $user): bool
    {
        return $user->role->canAdministerPlatform();
    }

    public function view(User $user, SendingPause $pause): bool
    {
        return $user->role->canAdministerPlatform();
    }

    /**
     * Stop a tenant deliberately — `SendingGuard::pause()` with a human actor.
     *
     * ⚠️ **THE AUTOMATIC TRIP DOES NOT COME THROUGH HERE AND MUST NOT.** 2102
     * requires it to fire *"without a human"*, and a policy is a question about
     * a `User`; `SendingGuard::shouldTrip()` calls `pause()` directly with
     * `SendingGuard::SYSTEM_ACTOR`. A containment that could only fire while
     * somebody was logged in would be the mitigation 2102 says this override
     * cannot rely on.
     */
    public function create(User $user): bool
    {
        return $user->role->canAdministerPlatform();
    }

    /**
     * Let a tenant send again.
     *
     * ⚠️ **A SEPARATE ABILITY FROM `create`, RATHER THAN BOTH ON `update`.**
     * They are not the same act and they will not always have the same answer:
     * 2408 makes release a person's decision with an actor, and the plausible
     * future change here is a narrower set of people who may lift a containment
     * than may apply one. Two names now means that change is one line rather
     * than an unpicking.
     */
    public function release(User $user, SendingPause $pause): bool
    {
        return $user->role->canAdministerPlatform();
    }

    /**
     * Nobody, and this is the retention rule from 2119(a) expressed as an
     * authority rather than as an absent form.
     *
     * A pause row is an **incident record**: `reason`, `tripped_by` — the one
     * thing telling 2102's automatic trip apart from an operator's deliberate
     * pause — and `observed_rate_bp`, a snapshot that **cannot be recomputed**
     * because the window has rolled by the time anybody asks. Editing one is
     * rewriting history, and `SendingGuard::resume()` is the only sanctioned
     * write to a live row.
     */
    public function update(User $user, SendingPause $pause): bool
    {
        return false;
    }

    /**
     * Nobody, for `update`'s reason and one more.
     *
     * ⛔ **DELETING A PAUSE ROW IS THE DEFECT 2119(a) EXISTS TO FIX.**
     * `resume()` called `$pause->delete()` until then, which destroyed the only
     * record of why a tenant had been stopped — and left the append-only audit
     * entry naming an `entity_id` that no longer resolved (2477). A delete
     * ability here would be that defect re-offered as a feature.
     */
    public function delete(User $user, SendingPause $pause): bool
    {
        return false;
    }
}
