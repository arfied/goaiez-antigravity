<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Storage\StorageRetention;

/**
 * Whether this application holds the audio of a voicemail.
 *
 * ⛔ **SEPARATE FROM {@see VoicemailTranscriptState}, AND THE SEPARATION IS THE
 * WHOLE POINT.** `CLAUDE.md`: *never block on transcription — STT failure still
 * delivers the audio.* One column carrying both would make "we have the
 * recording" and "we have the words" one fact, and the first thing a single
 * column does is hold the owner notification until the second arrives.
 */
enum VoicemailAudioState: string
{
    /** The carrier says a recording exists; we have not fetched it yet. */
    case Pending = 'pending';

    /** The bytes are on this application's own storage. */
    case Stored = 'stored';

    /**
     * We could not fetch it, and we have stopped trying.
     *
     * ⚠️ **THE OWNER IS STILL TOLD SOMEBODY RANG.** A recording we cannot
     * retrieve is a worse notification, not an absent one — the missed call, the
     * caller's number and the time are all facts this application already holds.
     */
    case Unavailable = 'unavailable';

    /**
     * We held the audio for the period an operator stated, and then deleted it —
     * {@see StorageRetention}, decision 4944.
     *
     * ⛔ **DISTINCT FROM {@see self::Unavailable}, AND COLLAPSING THE TWO WOULD
     * HAVE BEEN THE CHEAP WRONG ANSWER.** `Unavailable` means *we never got it*
     * — a carrier we could not reach, a fetch that ran out of retries — and it
     * is a fault. This means *we got it, we kept it, and the schedule came
     * round*, which is not. An operator asked "why is there no audio on this
     * call" needs to know which of the two they are looking at before they check
     * anything else, and the owner-facing sentence differs accordingly.
     *
     * ⚠️ **THE TRANSCRIPT IS NOT TOUCHED BY THIS AND THAT IS DELIBERATE.**
     * {@see VoicemailTranscriptState} is a separate column for the reason this
     * enum's own docblock gives, and the words are not the recording: pruning
     * the audio leaves the voicemail readable, which is what an owner scrolling
     * back through a year of missed calls actually uses. ⚠️ **So pruning the
     * audio is not erasure of what the caller said**, and `StorageRetention`'s
     * docblock says so in the one place somebody would otherwise assume it.
     */
    case Pruned = 'pruned';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Recording on its way',
            self::Stored => 'Recording ready',
            self::Unavailable => 'No recording',
            // Outcome language (`22`): what happened, in the words of the person
            // reading it, and never "retention policy applied".
            self::Pruned => 'Recording deleted after the keeping period',
        };
    }
}
