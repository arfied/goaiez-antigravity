<?php

declare(strict_types=1);

namespace App\Enums;

use App\Events\Voice\VoicemailTranscribed;

/**
 * Whether a voicemail has words attached to it.
 *
 * ⛔ **EVERY VALUE HERE IS AN ENRICHMENT AND NONE OF THEM IS A PRECONDITION.**
 * `CLAUDE.md`: *never block on transcription — STT failure still delivers the
 * audio.* {@see VoicemailTranscribed} may never fire and that
 * is a supported outcome; anything that reads this column to decide whether to
 * notify an owner has turned a speech-to-text outage into a silent missed-call
 * outage, and the tell is that every test passes because the fake transcriber
 * always answers.
 */
enum VoicemailTranscriptState: string
{
    /** Queued, or waiting on audio that has not been fetched yet. */
    case Pending = 'pending';

    /** Words exist. */
    case Transcribed = 'transcribed';

    /**
     * No transcriber is configured, or the one that is could not read this.
     *
     * ⚠️ **THIS IS THE DEFAULT OUTCOME TODAY AND IT IS NOT A FAILURE.** The
     * transcription vendor is undecided — `CLAUDE.md` leaves Whisper against
     * Deepgram open until Stage 6b — so the shipped driver answers nothing and
     * every voicemail lands here with its audio intact.
     */
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Being written up',
            self::Transcribed => 'Written up',
            self::Unavailable => 'Listen to the recording',
        };
    }
}
