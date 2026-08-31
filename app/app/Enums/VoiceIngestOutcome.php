<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What became of one voice webhook, once it was off the request path.
 *
 * ⚠️ **EVERY CASE EXCEPT `Recorded` IS A NON-EVENT RATHER THAN A FAILURE**, and
 * the distinction decides whether a queue retries. A vendor that cannot be
 * reached is worth another attempt; a call arriving on a number nobody owns will
 * arrive on the same number next time.
 */
enum VoiceIngestOutcome: string
{
    /** A call row was written or moved. */
    case Recorded = 'recorded';

    /**
     * The vendor could not be read.
     *
     * ⚠️ **INCLUDES "NOT ACTIVATED YET", WHICH IS THE STATE THIS PLATFORM IS IN
     * TODAY.** T176 §7 item 3 gates P2 on Infobip activating Voice/Calls, and
     * until then `NullVoiceProvider` answers null to everything — so a webhook
     * that somehow arrived would be verified, counted and recorded here.
     */
    case ProviderUnavailable = 'provider_unavailable';

    /**
     * The number called belongs to nobody on this platform.
     *
     * ⚠️ **NOT AN ERROR AND NOT RETRYABLE.** It is the shared Lane A pool number,
     * or a number we have released. `InboundThreading` treats the same condition
     * the same way and calls it a provisioning gap rather than a silent drop.
     */
    case UnknownNumber = 'unknown_number';

    /**
     * This call is already recorded in a settled state.
     *
     * ⚠️ **A REDELIVERED WEBHOOK IS ORDINARY, NOT AN INCIDENT.** Voice events
     * arrive twice and out of order; a `CALL_RECEIVED` replayed after the call
     * finished must not walk an `Answered` row back to `in_progress` and fire
     * the whole chain again.
     */
    case Duplicate = 'duplicate';

    /** The event type is one this application does not act on. */
    case Ignored = 'ignored';

    /**
     * A recording arrived before the call it belongs to (4518).
     *
     * ⛔ **THE ONE RETRYABLE OUTCOME ON THIS PATH, AND IT USED TO WEAR
     * `UnknownNumber`'S NAME.** Two different conditions shared that case: a
     * number nobody owns, which will still be nobody's next time, and a
     * recording notification that overtook its own call event, which the code
     * itself calls ordinary. Because the shared case is not retryable, the
     * ordinary one **ended the job successfully** — no voicemail row, no owner
     * notification, and nothing logged. `$tries = 3` and the backoff ladder were
     * never exercised at all.
     */
    case AwaitingCall = 'awaiting_call';

    /**
     * This tenant has told us not to touch their calls (4503).
     *
     * ⛔ **`CallRoutingMode::TrackingOnly` IS AN INSTRUCTION AND NOT A DEFAULT
     * TO STEP AROUND.** Its own words on the owner's screen are *"your phone
     * rings exactly as it does today — we measure what we can and change
     * nothing"*, and nothing on the ingest path read it until this. A tenant who
     * tried `Conditional`, dialled the code and then moved the radio back keeps
     * the carrier forward up — the screen's own copy admits we cannot see
     * carrier settings — so the calls keep arriving, and answering, recording,
     * storing and texting them is done against an explicitly recorded
     * instruction with the contradiction rendered on the same page.
     *
     * ⚠️ **NOT RETRYABLE, AND NOT AN ERROR.** The setting will say the same
     * thing on the next delivery.
     */
    case RefusedByRoutingMode = 'refused_by_routing_mode';
}
