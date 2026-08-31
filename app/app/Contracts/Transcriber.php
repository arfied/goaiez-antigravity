<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Services\Voice\NullTranscriber;
use App\Services\Voice\Transcript;

/**
 * Turning a voicemail into words — the seam, with no vendor behind it yet.
 *
 * ⛔ **THE VENDOR IS UNDECIDED AND THIS FILE DOES NOT DECIDE IT.** `CLAUDE.md`:
 * *"Batch transcription (Whisper large-v3 self-hosted or Deepgram) … **Still
 * open** — decide at Stage 6b."* So what ships is the interface and
 * {@see NullTranscriber}, and writing a Deepgram client here
 * because one was needed would be picking a subprocessor in a slice about
 * telephony — with a DPA and a `SUBPROCESSOR-INVENTORY.md` row that nobody
 * asked for.
 *
 * ⛔ **NOTHING MAY BLOCK ON THIS.** `CLAUDE.md`: *never block on transcription —
 * STT failure still delivers the audio.* {@see transcribe()} answers null for
 * every failure and for "no transcriber configured", which are the same thing to
 * every caller: the voicemail is delivered, the owner is notified, and the words
 * are an enrichment that may never arrive.
 *
 * ⚠️ **BATCH, NEVER LIVE.** This runs on a queued job against stored audio.
 * `CLAUDE.md` forbids an LLM on the synchronous context path and forbids a TTS
 * call during a call; the same reasoning applies to speech-to-text, and a live
 * transcription would additionally mean a vendor holding an open stream of a
 * member of the public's voice.
 */
interface Transcriber
{
    /**
     * @param  string  $audio  The stored bytes. ⚠️ **Never logged** — it is a
     *                         recording of a person speaking.
     * @param  string  $format  `wav`, `mp3` — what the vendor said the file is.
     * @return Transcript|null Null is a supported answer and is not an error.
     */
    public function transcribe(string $audio, string $format): ?Transcript;
}
