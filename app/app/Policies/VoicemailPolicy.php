<?php

declare(strict_types=1);

namespace App\Policies;

use App\Jobs\Voice\FetchVoicemailRecordingJob;
use App\Models\User;
use App\Models\Voicemail;

/**
 * Who may listen to a message a caller left this business (4519).
 *
 * ⚠️ **`InboundMediaPolicy`'S SHAPE AND ITS REASONING VERBATIM.** A policy
 * answers *"may this role do this?"*; the tenant boundary answers *"is this row
 * yours?"*, and conflating the two makes this class look like the boundary and
 * quietly become the place people trust instead of the global scope and the RLS
 * policy beneath it.
 *
 * ⚠️ **STAFF MAY LISTEN, AND THAT IS THE FEATURE.** R7's whole promise is that
 * somebody at the business hears what the caller wanted; a capability gate here
 * would mean the person who answers the phone is the one who cannot play the
 * message they missed.
 *
 * ⛔ **THERE IS NO `create`, `update` OR `delete`.** Nobody signed in creates a
 * voicemail — {@see FetchVoicemailRecordingJob} does, off a carrier webhook —
 * and no screen edits one. Adding the methods would describe controls that do
 * not exist, which is the shape `CLAUDE.md` records as a protection layer
 * asserted before it is true.
 *
 * ⛔ **AND THIS IS NOT THE PHI GATE.** A covered entity's voicemail audio is
 * never fetched and never stored (4500), so there is no object for this policy
 * to be asked about — and `Voicemail::isPlayable()` refuses on the state rather
 * than on the file. **The open question this does not answer is
 * minimum-necessary access** (4504): every user of a tenant can already read
 * every caller's number and every transcript on `Account\Calls`, and whether
 * that is right for a health-adjacent business is the owner's call rather than
 * this file's.
 */
final class VoicemailPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Voicemail $voicemail): bool
    {
        return true;
    }
}
