<?php

declare(strict_types=1);

namespace App\Modules\X66\Events;

final class VoicemailTranscribed
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $voicemailId,
        public readonly string $transcription
    ) {}
}
