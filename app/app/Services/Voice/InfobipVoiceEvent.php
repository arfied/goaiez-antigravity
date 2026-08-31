<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Enums\VoiceWebhookEvent;

/**
 * Reading Infobip's Calls event webhook — the one place its vocabulary is
 * touched.
 *
 * ## ⛔ WHAT THE VENDOR ACTUALLY PUBLISHES, READ 2026-08-16
 *
 *   https://www.infobip.com/docs/api/channels/voice/calls/calls-applications/calls-event-webhook
 *
 * The `Event` schema has **eight top-level fields and nothing else**:
 * `conferenceId` · `callId` · `timestamp` · `callsConfigurationId` ·
 * `platform` (`entityId`, `applicationId`) · `bulkId` · `dialogId` · `type`.
 *
 * ⛔ **THERE IS NO PER-EVENT PAYLOAD IN THE PUBLISHED SCHEMA.** The reference
 * lists 62 `EventType` values and defines no `properties` object, no
 * discriminated union and no per-type sub-schema — so **the caller's number is
 * not a documented field of this webhook.** That is why nothing here parses one:
 * 4256–4261 is this exact vendor's inbound MMS payload, where two plausible
 * spellings of the media key were guessed, **both were wrong**, and nothing
 * broke — the handler simply captured nothing, with a green suite.
 * {@see VoiceCalls} reads the call back from `GET /calls/1/calls/{callId}`, whose
 * response *is* fully documented, and this class extracts only what the webhook
 * genuinely carries: which call, and which read to make about it.
 *
 * ⚠️ **A REAL BODY MAY WELL CARRY MORE THAN THE SCHEMA DOES**, and that is
 * precisely why nothing here reaches for it. An undocumented field that happens
 * to be present today is a field the vendor may rename tomorrow with no
 * changelog, and the failure mode — as 4256 proved — is silence rather than an
 * error. If a real delivery turns out to carry the endpoints, the right change
 * is to read the payload against that artefact and record the citation, not to
 * guess ahead of one.
 *
 * ## The type mapping
 *
 * ⚠️ **THREE OF 62 TYPES ARE ACTED ON AND THE REST ARE IGNORED, DELIBERATELY.**
 * An unrecognised type is a 200 with nothing done rather than an error, because
 * a vendor adding an event must not turn into an endpoint that starts failing.
 *
 * ⛔ **`CALL_RECEIVED` IS NOT ACTED ON, AND THAT IS NOT AN OMISSION.** It fires
 * before anything has happened; acting on it would write a row whose outcome is
 * `in_progress` and be superseded moments later, at the cost of a vendor read
 * per call.
 */
final class InfobipVoiceEvent
{
    /**
     * The vendor's event names, verbatim from the reference above.
     *
     * @var array<string, VoiceWebhookEvent>
     */
    private const array TYPES = [
        'CALL_FINISHED' => VoiceWebhookEvent::CallEnded,
        'CALL_FAILED' => VoiceWebhookEvent::CallEnded,
        'CALL_RECORDING_READY' => VoiceWebhookEvent::RecordingReady,
    ];

    /**
     * The vendor's call handle, or null when the body does not carry one.
     *
     * @param  array<string, mixed>  $body
     */
    public static function callId(array $body): ?string
    {
        $id = $body['callId'] ?? null;

        return is_string($id) && trim($id) !== '' ? trim($id) : null;
    }

    /**
     * Which read this notification asks for, or null for one we do not act on.
     *
     * @param  array<string, mixed>  $body
     */
    public static function event(array $body): ?VoiceWebhookEvent
    {
        $type = $body['type'] ?? null;

        if (! is_string($type)) {
            return null;
        }

        return self::TYPES[mb_strtoupper(trim($type))] ?? null;
    }
}
