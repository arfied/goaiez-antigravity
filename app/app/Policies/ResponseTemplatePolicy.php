<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ResponseTemplate;
use App\Models\User;

/**
 * Who may write the examples the reply drafter copies, and who may only read
 * them.
 *
 * THE TENANT IS NOT CHECKED HERE, deliberately — `KnowledgeSourcePolicy`'s
 * reasoning verbatim, and `AutopilotSettingsPolicy`'s before it. A policy
 * answers *"may this role do this?"*; the tenant boundary answers *"is this row
 * yours?"*, and conflating the two makes this class look like the boundary and
 * quietly become the place people trust instead of the global scope and the RLS
 * policy underneath it. A `ResponseTemplate` can only be resolved inside its own
 * tenant, so by the time one reaches this class the boundary has already held.
 *
 * ⚠️ **`canConfigureAutomation()`, NOT A NEW CAPABILITY, AND THE REASON IS WHAT
 * WRITING ONE ACTUALLY DOES.** It looks like saving a note. What it adds is the
 * material a model imitates when it writes, under the business's own name, on
 * the business's public Google listing — the same class of act as changing what
 * the automation does. `KnowledgeSourcePolicy` reached the same answer about an
 * uploaded document for the same reason, and a `staff` user who may read what
 * the drafter was shown may not rewrite it.
 *
 * ⚠️ **DELETE IS OPEN ON THE SAME TERMS AS `KnowledgeSourcePolicy`'s.** Removing
 * a bad example is how an owner takes back something they should not have
 * written; the role that may add is the role that may remove.
 *
 * ⚠️ **THERE IS NO `update`, AND ITS ABSENCE IS A DESIGN RATHER THAN A GAP.**
 * `ResponseTemplates` offers add and remove and nothing else, so an `update`
 * here would be a permission over an act no code can perform — a policy method
 * nothing asks reads as enforcement and is not any (314–316), which is the rule
 * `AuthorizationTest`'s uncalled-policy lint enforces one level up.
 */
final class ResponseTemplatePolicy
{
    /**
     * Everyone signed in to a business may see the examples it curated.
     *
     * Staff included, on `KnowledgeSourcePolicy::viewAny()`'s reasoning: they
     * work the same inbox the drafter fills, and hiding what it was shown leaves
     * the person handling the replies unable to explain why they read as they
     * do.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ResponseTemplate $template): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role->canConfigureAutomation();
    }

    public function delete(User $user, ResponseTemplate $template): bool
    {
        return $user->role->canConfigureAutomation();
    }
}
