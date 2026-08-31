<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SupportSetting;
use App\Models\User;

/**
 * Who may change how a business's calls are handled.
 *
 * THE TENANT IS NOT CHECKED HERE, deliberately — `AutopilotSettingsPolicy`'s
 * reasoning verbatim, and `KnowledgeSourcePolicy` repeats it for the same
 * reason. A policy answers "may this role do this?"; the tenant boundary answers
 * "is this row yours?", and conflating the two makes this class look like the
 * boundary and quietly become the place people trust instead of the global scope
 * and the RLS policy underneath it. A `SupportSetting` can only be resolved
 * inside its own tenant, so by the time one reaches this class the boundary has
 * already held.
 *
 * ⚠️ **CHANGING THIS IS `canConfigureAutomation()`, AND IT IS THE HIGHEST-BLAST
 * -RADIUS SETTING A TENANT HAS.** `CallRoutingMode`'s own docblock says it:
 * everything above `TrackingOnly` intercepts a real customer call, and *"a
 * misconfiguration here is a business that stops receiving calls, which is worse
 * than any feature it enables"*. That is the same class of act as changing what
 * the automation does — so it takes the same role, and a `staff` user who may
 * see how calls are handled may not move it.
 */
final class SupportSettingPolicy
{
    /**
     * Everyone signed in to a business may see how its calls are handled.
     *
     * Staff included, and for a sharper reason than the other two policies give:
     * a member of staff answering the phone needs to know whether an unanswered
     * call reaches us or reaches the carrier's mailbox. Hiding it produces
     * guesswork about where a customer's message went.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SupportSetting $settings): bool
    {
        return true;
    }

    /**
     * The row is created by the same act that updates it, so both answer alike.
     *
     * `CallForwarding::chooseMode()` creates this tenant's row on their first
     * choice — see that class for why it is lazy rather than seeded at
     * provisioning. A `create` that answered differently from `update` would
     * make a staff user's first save succeed and their second fail.
     */
    public function create(User $user): bool
    {
        return $user->role->canConfigureAutomation();
    }

    public function update(User $user, SupportSetting $settings): bool
    {
        return $user->role->canConfigureAutomation();
    }

    /**
     * Nobody, by design — `AutopilotSettingsPolicy::delete()`'s reasoning.
     *
     * There is one row per business and every column on it has a default that is
     * already the safe answer. Deleting it does not "reset to defaults", and the
     * operation that looks like it is wanted here is a save of
     * `CallRoutingMode::TrackingOnly`, which is a control the screen already
     * offers.
     */
    public function delete(User $user, SupportSetting $settings): bool
    {
        return false;
    }
}
