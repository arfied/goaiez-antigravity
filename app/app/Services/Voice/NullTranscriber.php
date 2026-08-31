<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Contracts\Transcriber;
use App\Enums\VoicemailTranscriptState;

/**
 * The transcriber that transcribes nothing — the default, and the honest one.
 *
 * ⛔ **IT IS NOT A STUB WAITING TO BE FILLED IN, IT IS THE SHIPPED BEHAVIOUR.**
 * The transcription vendor is an open decision (`CLAUDE.md`: Whisper against
 * Deepgram, *"still open — decide at Stage 6b"*), so every voicemail today lands
 * on {@see VoicemailTranscriptState::Unavailable} **with its audio
 * intact and its owner already notified**. That is exactly what *"never block on
 * transcription"* is supposed to look like when the transcriber is missing, and
 * it means the day a vendor is chosen the only change is a driver binding.
 *
 * ⚠️ **THIS IS ALSO WHAT MAKES THE RULE TESTABLE.** `VoicemailRecorded` warns
 * that *"the tell is that every test passes because the fake transcriber always
 * answers"*. The default driver here answers nothing, so the suite exercises the
 * failure path by default and a test that wants words has to bind a fake.
 */
final class NullTranscriber implements Transcriber
{
    public function transcribe(string $audio, string $format): ?Transcript
    {
        return null;
    }
}
