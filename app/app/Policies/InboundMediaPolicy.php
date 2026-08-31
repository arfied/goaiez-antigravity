<?php

declare(strict_types=1);

namespace App\Policies;

use App\Jobs\Sms\CaptureInboundMediaJob;
use App\Models\InboundMedia;
use App\Models\User;

/**
 * Who may look at a picture a customer texted this business.
 *
 * THE TENANT IS NOT CHECKED HERE, deliberately — `TenantLinkRecordPolicy`'s and
 * `KnowledgeSourcePolicy`'s reasoning verbatim. A policy answers *"may this role
 * do this?"*; the tenant boundary answers *"is this row yours?"*, and conflating
 * the two makes this class look like the boundary and quietly become the place
 * people trust instead of the global scope and the RLS policy underneath it.
 *
 * ⚠️ **STAFF MAY SEE IT, AND THAT IS THE POINT OF THE FEATURE.** Skill 12 is
 * photo intake: somebody texts a picture of the broken thing, and whoever
 * answers the Inbox needs to see it. A capability gate here would mean the
 * person actually holding the conversation is the one who cannot open the
 * attachment.
 *
 * ⛔ **THERE IS NO `create`, `update` OR `delete`, AND THEIR ABSENCE IS THE
 * RULE.** Nobody signed in creates one of these — {@see CaptureInboundMediaJob}
 * does, on a carrier webhook, with `system:` as the actor — and the model
 * refuses updates outright. Adding the methods would describe controls that do
 * not exist, which is the shape `CLAUDE.md` records as a protection layer
 * asserted before it is true.
 *
 * ⚠️ **AND NOTHING HERE IS THE PHI GATE.** A PHI tenant's inbound media is never
 * stored at all (4166), so there is no row for this policy to be asked about. If
 * that ruling is ever reversed, the gate belongs at the capture, not here — a
 * policy that quietly became the only thing standing between a support agent and
 * a patient's photograph is the layer nobody would think to check.
 */
final class InboundMediaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, InboundMedia $media): bool
    {
        return true;
    }
}
