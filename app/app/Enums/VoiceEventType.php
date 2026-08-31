<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The things that can happen on a call this application knows about.
 *
 * **Every case is inbound**, and what holds it is `SendContractTest`'s literal
 * assertion of these five values together with the `match` that has no default
 * (2175) — `29` §2.3 rule 13 itself was **overridden by an owner ruling on
 * 2026-08-25** (9363) and is no longer what refuses one. 2103 confirms the
 * missed-call design does not touch that —
 * conditional forwarding brings a call *to* the tenant's number, and the only
 * thing that leaves this system afterwards is an SMS. ⚠️ **There is deliberately
 * no `Dialled`, no `Originated` and no `Callback` case**, and adding one is not
 * an enum change: it is a request to build outbound calling, and the answer is
 * the rule above.
 *
 * ## This vocabulary is ours, not Infobip's
 *
 * The vendor's own webhook event names are mapped onto these at the edge, in one
 * place, the way `OutreachStatus` is mapped from the delivery-receipt
 * vocabulary rather than stored raw. 255, 277, 684 and 1349 are four recorded
 * occasions where a vendor string was written from memory and was wrong; a
 * vendor string spread across the application is four more chances at it.
 *
 * ## The ordering is not guaranteed and the design assumes it is not
 *
 * ⛔ **`VoicemailTranscribed` MAY NEVER ARRIVE, AND THAT IS A SUPPORTED
 * OUTCOME.** `CLAUDE.md`: *never block on transcription — STT failure still
 * delivers the audio.* So `VoicemailRecorded` is what notifies the owner and
 * `MissedCall` is what fires the text-back; a transcript, when it comes, is an
 * enrichment of a notification that already went out.
 */
enum VoiceEventType: string
{
    /**
     * A call arrived on one of our numbers and we answered it.
     *
     * ⚠️ **The recording announcement is played here, unconditionally, in every
     * state** (2104). It is part of the pre-rendered greeting, not a separate
     * decision and not a per-state configuration — the announcement is
     * unconditional precisely so it never becomes one. And it is pre-rendered
     * static audio: `CLAUDE.md` forbids a TTS call during a call.
     */
    case Answered = 'answered';

    /**
     * The call ended without the business speaking to the caller.
     *
     * **This is the event the product turns on.** It fires the SM-001 text-back
     * to the caller from the tenant's own number and hands the resulting SMS
     * thread to the conversation lane. It fires whether or not a voicemail was
     * left, and whether or not a transcript ever exists.
     */
    case Missed = 'missed';

    /**
     * The caller left a voicemail and the audio is stored.
     *
     * Triggers the owner notification by email and/or SMS per tenant setting.
     * ⚠️ **Not gated on a transcript.**
     */
    case VoicemailRecorded = 'voicemail_recorded';

    /**
     * A transcript exists for a voicemail already recorded.
     *
     * ⚠️ **Enrichment, never a precondition.** An implementation that waits for
     * this before notifying an owner has made a speech-to-text outage into a
     * silent missed-call outage.
     */
    case VoicemailTranscribed = 'voicemail_transcribed';

    /**
     * The call was forwarded onward and someone picked it up.
     *
     * R7's optional mode — re-forward or ring-back instead of voicemail.
     * ⚠️ **This is still not outbound calling**: the leg is a continuation of an
     * inbound call the caller placed, and nothing in this system may originate
     * one.
     */
    case Forwarded = 'forwarded';

    /**
     * Whether this event means the business did not speak to the caller, and so
     * the text-back is owed.
     *
     * A match with no default, so a sixth case is a compile-time conversation
     * rather than one that quietly inherits "no text-back" and turns the
     * product's core promise off for a whole category of call.
     */
    public function owesTextBack(): bool
    {
        return match ($this) {
            self::Missed => true,
            self::Answered, self::VoicemailRecorded, self::VoicemailTranscribed, self::Forwarded => false,
        };
    }
}
