<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TenantLinkRecord;
use App\Models\User;

/**
 * Who decides where this business's assistant sends its customers.
 *
 * THE TENANT IS NOT CHECKED HERE, deliberately — `KnowledgeSourcePolicy`'s
 * reasoning verbatim. A policy answers "may this role do this?"; the tenant
 * boundary answers "is this row yours?", and conflating the two makes this class
 * look like the boundary and quietly become the place people trust instead of the
 * global scope and the RLS policy underneath it.
 *
 * ⚠️ **SETTING A LINK IS `canConfigureAutomation()`, ON THE SAME ARGUMENT AS
 * UPLOADING A DOCUMENT TO THE BRAIN, AND IT IS STRONGER HERE.** What a link
 * changes is not what the assistant *says* but where it *sends people* — a
 * payment URL is where a customer will be asked to put their card details in the
 * business's name, and a fee is a figure the assistant will quote as the
 * business's own. That is the same class of act as changing what the automation
 * does, so a `staff` user who may see the links may not move them.
 *
 * ⚠️ **DELETE IS OPEN, ON `KnowledgeSourcePolicy`'s REASONING.** Removing a link
 * is how an owner takes back a payment page that is no longer theirs or a
 * document that should not have been shared; making that impossible would leave a
 * support ticket as the only remedy for the fastest-moving mistake on this
 * screen. The same role that may add is the role that may remove.
 */
final class TenantLinkRecordPolicy
{
    /**
     * Everyone signed in to a business may see what it hands out.
     *
     * Staff included: they answer the same customers from the Inbox, and hiding
     * what the assistant sends produces two different answers to one question.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TenantLinkRecord $link): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role->canConfigureAutomation();
    }

    public function update(User $user, TenantLinkRecord $link): bool
    {
        return $user->role->canConfigureAutomation();
    }

    public function delete(User $user, TenantLinkRecord $link): bool
    {
        return $user->role->canConfigureAutomation();
    }
}
