<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The two things a voice webhook can tell this application to go and do.
 *
 * ⛔ **THIS IS NOT {@see VoiceEventType} AND CONFLATING THE TWO IS THE DEFECT IT
 * EXISTS TO PREVENT.** `VoiceEventType` is what *happened on a call* — answered,
 * missed, voicemail recorded — and `29` §19.6 makes getting it wrong
 * build-failing in both directions. This enum is only *which vendor notification
 * arrived*, and a vendor notification cannot answer the first question: Infobip
 * sends `CALL_FINISHED` for a call the business answered and for one that rang
 * out. The first draft of this path mapped `CALL_FINISHED` straight onto
 * `VoiceEventType::Missed`, which reads as a harmless routing shortcut and is in
 * fact *"the gate that decides whether a stranger gets texted, decided by a
 * string"*.
 *
 * ⚠️ **SO THE OUTCOME IS ALWAYS READ BACK FROM THE CALL** —
 * {@see CallOutcome::fromProviderState()}, from the vendor's own `state` and
 * whether the leg was ever answered — and this enum only says which read to make.
 */
enum VoiceWebhookEvent: string
{
    /**
     * A call reached a terminal state; go and find out which one.
     *
     * Infobip's `CALL_FINISHED` and `CALL_FAILED`, verified 2026-08-16 against
     * https://www.infobip.com/docs/api/channels/voice/calls/calls-applications/calls-event-webhook
     */
    case CallEnded = 'call_ended';

    /**
     * A recording file is available to fetch.
     *
     * Infobip's `CALL_RECORDING_READY`. ⚠️ **Not `CALL_RECORDING_STOPPED`**,
     * which is the recorder being turned off — acting on that would ask for a
     * file before there is one.
     */
    case RecordingReady = 'recording_ready';
}
