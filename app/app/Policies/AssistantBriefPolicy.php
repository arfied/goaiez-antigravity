<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AssistantBrief;
use App\Models\PriceListItem;
use App\Models\UrgentTerm;
use App\Models\User;

/**
 * Who decides what this business's assistant quotes and what wakes its owner.
 *
 * THE TENANT IS NOT CHECKED HERE, deliberately — `TenantLinkRecordPolicy`'s
 * reasoning verbatim, which is `KnowledgeSourcePolicy`'s. A policy answers "may
 * this role do this?"; the tenant boundary answers "is this row yours?", and
 * conflating the two makes this class look like the boundary and quietly become
 * the place people trust instead of the global scope and the RLS policy
 * underneath it.
 *
 * ⚠️ **ONE POLICY GOVERNS THREE TABLES, AND THAT IS ARGUED RATHER THAN LAZY.**
 * {@see PriceListItem}, {@see UrgentTerm} and {@see AssistantBrief} are one
 * answer sheet, given in one wizard step (§2.4), by one person, about one
 * question: *what may the assistant say in your name?* Three policies with
 * identical bodies are three places for that one rule to drift, and the drift
 * would be silent — a `staff` user refused on prices and allowed on urgent terms
 * looks like a working screen. The subject named at every call site is
 * `AssistantBrief::class`, which is the thing being changed in every case.
 *
 * ⚠️ **SETTING A PRICE IS `canConfigureAutomation()`, AND IT IS THE STRONGEST
 * CASE ON THAT LIST YET.** A price list is not what the assistant *says* about
 * the business, it is **a figure quoted to a member of the public in the
 * business's own name** — R13 lets skill 4 state it flatly. An urgent term is
 * the other half: it decides what wakes the owner at 3am. Both are the same
 * class of act as changing what the automation does, so a `staff` user who may
 * see them may not move them.
 *
 * ⚠️ **DELETE IS OPEN TO THE SAME ROLE THAT MAY ADD**, on
 * `KnowledgeSourcePolicy`'s reasoning: removing a price the business no longer
 * charges is the remedy for the fastest-moving mistake on this screen, and
 * making it impossible would leave a support ticket as the only way out of a
 * wrong figure that is being quoted right now.
 */
final class AssistantBriefPolicy
{
    /**
     * Everyone signed in to a business may see what it quotes.
     *
     * Staff included: they answer the same customers from the Inbox, and hiding
     * the price list produces two different answers to one question.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AssistantBrief $brief): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role->canConfigureAutomation();
    }

    public function update(User $user, AssistantBrief $brief): bool
    {
        return $user->role->canConfigureAutomation();
    }

    public function delete(User $user, AssistantBrief $brief): bool
    {
        return $user->role->canConfigureAutomation();
    }
}
