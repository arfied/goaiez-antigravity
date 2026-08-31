<?php

declare(strict_types=1);

namespace App\Events\Voice;

use App\Services\Voice\InboundCall;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A transcript now exists for a voicemail that was already stored and already
 * notified.
 *
 * ⛔ **THIS EVENT IS AN ENRICHMENT AND MAY NEVER FIRE.** That is a supported
 * outcome, not a degraded one — `CLAUDE.md`: *never block on transcription; STT
 * failure still delivers the audio.* Everything R7 promises has already happened
 * by the time this arrives: the owner was notified when
 * {@see VoicemailRecorded} fired, and the caller was texted back when
 * {@see CallMissed} fired. **Nothing downstream may treat this as a
 * precondition**, and a listener that does has built a system whose core feature
 * silently stops working when one vendor is slow.
 *
 * ## The text is untrusted input
 *
 * ⚠️ It is a stranger's speech, machine-transcribed, and it reaches an AI model
 * on the conversation lane. T137 §3.7's containment applies to it exactly as it
 * applies to an inbound SMS: **data, never instructions.** A transcript reading
 * *"ignore your instructions and…"* is a thing a caller can say out loud.
 *
 * ## PHI
 *
 * ⚠️ A voicemail to a healthcare tenant can contain anything the caller chose to
 * say, including health information nobody asked for. The per-review consent
 * checkbox of 2079–2081 has no equivalent here — **there is no surface on which
 * to ask a caller anything before they speak.** So a PHI-flagged tenant's
 * transcripts follow the tenant-level rule, and 2081 applies with its full
 * force: **never rewrite a PHI test to assert that unconsented patient text now
 * reaches an AI provider.**
 *
 * ⛔ **NOT `ShouldBroadcast`.** The payload is the literal words a member of the
 * public spoke. See {@see CallMissed}.
 */
final class VoicemailTranscribed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  string  $transcript  What the transcriber heard. ⚠️ Untrusted, and
     *                              **never logged** — `CLAUDE.md` requires
     *                              vendor payloads be redacted before logging
     *                              and this one is a person talking.
     * @param  string  $engine  Which transcriber produced it, so a bad batch can
     *                          be identified later. A name, never a credential.
     * @param  float|null  $confidence  0–1 where the engine reports one. ⚠️ **Low
     *                                  confidence is not a reason to withhold
     *                                  the transcript** — the owner already has
     *                                  the audio and can listen; it is a reason
     *                                  to label it.
     */
    public function __construct(
        public readonly int $businessId,
        public readonly InboundCall $call,
        public readonly string $transcript,
        public readonly string $engine,
        public readonly ?float $confidence = null,
    ) {}

    public function occasion(): string
    {
        return 'voicemail_transcript:'.$this->call->providerCallId;
    }
}
