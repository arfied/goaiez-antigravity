<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\KnowledgeSource;
use App\Models\User;

/**
 * Who may teach the Business Brain, and who may only read what it was taught.
 *
 * THE TENANT IS NOT CHECKED HERE, deliberately — AutopilotSettingsPolicy's
 * reasoning verbatim. A policy answers "may this role do this?"; the tenant
 * boundary answers "is this row yours?", and conflating the two makes this class
 * look like the boundary and quietly become the place people trust instead of
 * the global scope and the RLS policy underneath it. A `KnowledgeSource` can
 * only be resolved inside its own tenant, so by the time one reaches this class
 * the boundary has already held.
 *
 * ⚠️ **UPLOADING IS `canConfigureAutomation()`, NOT A NEW CAPABILITY, AND THE
 * REASON IS WHAT AN UPLOAD ACTUALLY DOES.** It looks like adding a file; what it
 * adds is the material an automated agent will answer a customer from in the
 * tenant's own name. That is the same class of act as changing what the
 * automation does — so it takes the same role, and a `staff` user who may read
 * the Brain may not rewrite what it believes.
 *
 * ⚠️ **DELETE IS OPEN AND THAT IS NOT AN OVERSIGHT.** The counter-argument to
 * `AutopilotSettingsPolicy::delete()`'s permanent `false`: there, deleting a row
 * removes the defaults an automation reads on every run. Here, removing a
 * source is how an owner takes back a document they should not have uploaded —
 * a wrong price list, a stale policy, a file with a patient's name in it — and
 * making that impossible would mean the only remedy for a bad upload is a
 * support ticket. The same role that may add is the role that may remove.
 */
final class KnowledgeSourcePolicy
{
    /**
     * Everyone signed in to a business may see what it has been taught.
     *
     * Staff included: they answer customers alongside the bot, and hiding what
     * it knows produces two different answers to the same question.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, KnowledgeSource $source): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role->canConfigureAutomation();
    }

    public function update(User $user, KnowledgeSource $source): bool
    {
        return $user->role->canConfigureAutomation();
    }

    public function delete(User $user, KnowledgeSource $source): bool
    {
        return $user->role->canConfigureAutomation();
    }
}
