<?php

declare(strict_types=1);

namespace App\Events\Voice;

use App\Services\Voice\InboundCall;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A caller left a voicemail and the audio is stored.
 *
 * R7's owner-notification trigger: the recording is delivered to the business
 * owner by email and/or SMS, per the tenant's own setting.
 *
 * ⛔ **THIS FIRES ON THE AUDIO, NEVER ON THE TRANSCRIPT.** `CLAUDE.md`: *never
 * block on transcription — STT failure still delivers the audio.* An
 * implementation that holds the owner notification until
 * {@see VoicemailTranscribed} arrives has turned a speech-to-text outage into a
 * silent missed-call outage, and the tell is that every test passes because the
 * fake transcriber always answers.
 *
 * ⚠️ **THE RECORDING ANNOUNCEMENT ALREADY HAPPENED, IN THE GREETING** (2104). It
 * is played on every recorded call in every state, unconditionally, from
 * pre-rendered static audio — `CLAUDE.md` forbids a TTS call during a call. It
 * is not a field on this event and it is not a thing a listener checks: it is a
 * property of the greeting that was played before the caller could speak.
 *
 * ⛔ **NOT `ShouldBroadcast`.** The payload names a stored recording of a member
 * of the public's voice and the number they called from. See {@see CallMissed}.
 */
final class VoicemailRecorded implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  string  $recordingPath  Where the audio lives on this
     *                                 application's own storage — R2, per
     *                                 `CLAUDE.md`. ⚠️ **A path, never a vendor
     *                                 URL**: a vendor's recording URL expires,
     *                                 is often unauthenticated, and would be
     *                                 emailed to an owner as a link anybody who
     *                                 saw it could open.
     * @param  int|null  $durationSeconds  Null when the vendor did not say.
     */
    public function __construct(
        public readonly int $businessId,
        public readonly InboundCall $call,
        public readonly string $recordingPath,
        public readonly ?int $durationSeconds = null,
    ) {}

    /**
     * The idempotency occasion for work keyed to this voicemail.
     *
     * Distinct from {@see CallMissed::occasion()} on purpose: the owner
     * notification and the caller text-back are two different sends about one
     * call, and a shared occasion would make the second of them a duplicate of
     * the first.
     */
    public function occasion(): string
    {
        return 'voicemail:'.$this->call->providerCallId;
    }
}
