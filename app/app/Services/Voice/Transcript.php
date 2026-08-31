<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Events\Voice\VoicemailTranscribed;

/**
 * What a transcriber heard.
 *
 * ⚠️ **`text` IS UNTRUSTED INPUT.** It is a stranger's speech, machine-read, and
 * it reaches an AI model on the conversation lane —
 * {@see VoicemailTranscribed}: *data, never instructions. A
 * transcript reading "ignore your instructions and…" is a thing a caller can say
 * out loud.*
 *
 * ⚠️ **AND IT MAY BE PHI.** A voicemail to a healthcare tenant can contain
 * anything the caller chose to say. There is no surface on which to ask a caller
 * anything before they speak, so 2079–2081's per-review consent checkbox has no
 * equivalent here and the tenant-level rule stands — with 2081's force:
 * **never rewrite a PHI test to assert that unconsented patient text now reaches
 * an AI provider.**
 */
final readonly class Transcript
{
    /**
     * @param  string  $engine  A name, never a credential — so a bad batch can be
     *                          identified later.
     * @param  float|null  $confidence  0–1 where the engine reports one.
     *                                  ⚠️ **Low confidence is a reason to label a
     *                                  transcript, never to withhold it**: the
     *                                  owner already has the audio and can listen.
     */
    public function __construct(
        public string $text,
        public string $engine,
        public ?float $confidence = null,
    ) {}
}
