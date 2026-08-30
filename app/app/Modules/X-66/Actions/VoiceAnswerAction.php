<?php

declare(strict_types=1);

namespace App\Modules\X66\Actions;

use App\Modules\X66\Domain\VoiceSessionEngine;

final class VoiceAnswerAction
{
    public function __construct(private readonly VoiceSessionEngine $engine) {}

    public function handle(int $businessId, int $sessionId, int $latencyMs = 350): array
    {
        return $this->engine->handleAnswer($businessId, $sessionId, $latencyMs);
    }
}
