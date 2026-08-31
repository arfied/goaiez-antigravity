<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\TriageConversation;
use App\Models\User;

/**
 * Who may work a recovery conversation.
 *
 * Auto-discovered by name — there is no `Gate::policy()` call anywhere in this
 * application, the same as `AutopilotSettingsPolicy`.
 *
 * THE TENANT IS NOT CHECKED HERE, on that policy's stated reasoning: a policy
 * answers *may this role do this*, the tenant boundary answers *is this row
 * yours*, and conflating them makes this class the place people trust instead
 * of the scope. A `TriageConversation` resolves only inside its own tenant —
 * the global scope hides it and RLS blocks the query underneath — and
 * `ReviewRouter::assertConversationBelongsToTenant()` refuses a foreign row
 * that arrived some other way.
 */
final class TriageConversationPolicy
{
    /**
     * Everyone signed in to a business may see who is unhappy.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TriageConversation $conversation): bool
    {
        return true;
    }

    /**
     * Recording an outcome is doing the work, not configuring the system.
     *
     * ⚠️ **DELIBERATELY NOT `canConfigureAutomation()`, WHICH IS THE OBVIOUS
     * INSTINCT AND WOULD BE WRONG** (2707). That predicate exists for FOUND-04's
     * *"a `staff` user cannot change autopilot settings"* — a rule about who
     * decides **how the system behaves**. Ringing an unhappy customer back is
     * `staff`'s own job description in {@see UserRole} (*"does the
     * work"*), and gating it would mean the person who actually made the
     * recovery call cannot record it. The number on the owner's Home screen
     * would then stay at zero for exactly the tenants who are doing the most
     * about it — which is decision 2689's defect rebuilt one layer up.
     *
     * ⚠️ **AND IT IS NOT GATED ON `isTenantRole()` EITHER, BECAUSE THAT REFUSAL
     * COULD NEVER FIRE.** Internal staff belong to no business, so
     * `Tenancy::id()` is null and the component's own `abort_if` refuses them
     * before a policy is consulted; inside an impersonation session the acting
     * identity is the owner, so the predicate would be asked of the owner's
     * role and never of theirs. A conjunct that cannot fail is CLAUDE.md's 398
     * — an unfalsifiable inner guard sitting behind an outer one that already
     * said no — and writing it here would read as protection that is not there.
     */
    public function update(User $user, TriageConversation $conversation): bool
    {
        return true;
    }

    /**
     * Nobody, ever.
     *
     * ⛔ **THE ONE REFUSAL IN THIS FILE THAT IS LOAD-BEARING, AND IT IS
     * COMPLIANCE RATHER THAN TIDINESS** (2708). This row hangs off a `reviews`
     * row, and 2075's replacement build-failing rule is that **every rating is
     * captured and kept, and none is ever deleted, suppressed or hidden** (FTC
     * §465.7 prohibits suppressing reviews). A delete here would be the one
     * surface on which the private feedback nobody else sees could be made to
     * go away, reached from the screen whose whole subject is feedback somebody
     * would rather did not exist. Working a conversation moves its status;
     * nothing removes it, and `recoveryQueue()` goes on returning it.
     */
    public function delete(User $user, TriageConversation $conversation): bool
    {
        return false;
    }
}
