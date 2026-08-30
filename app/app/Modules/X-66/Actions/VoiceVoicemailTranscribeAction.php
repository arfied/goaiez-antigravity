<?php

declare(strict_types=1);

namespace App\Modules\X66\Actions;

use App\Modules\X66\Domain\VoiceSessionEngine;
use App\Modules\X66\Models\Voicemail;

final class VoiceVoicemailTranscribeAction
{
    public function __construct(private readonly VoiceSessionEngine $engine) {}

    public function handle(int $businessId, int $sessionId, string $audioUrl, string $transcription): Voicemail
    {
        return $this->engine->handleVoicemail($businessId, $sessionId, $audioUrl, $transcription);
    }
}
