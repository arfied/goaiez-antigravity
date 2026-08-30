<?php

declare(strict_types=1);

namespace App\Modules\X171\Actions;

final class NoteVoiceAction
{
    public function handle(int $businessId, int $jobId, string $audioTranscript): array
    {
        return ['status' => 'voice_note_transcribed', 'transcript' => $audioTranscript];
    }
}
