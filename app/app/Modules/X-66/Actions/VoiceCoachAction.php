<?php

declare(strict_types=1);

namespace App\Modules\X66\Actions;

use App\Modules\X66\Domain\VoiceSessionEngine;
use App\Modules\X66\Models\CallAutopsy;

final class VoiceCoachAction
{
    public function __construct(private readonly VoiceSessionEngine $engine) {}

    public function handle(int $businessId, int $sessionId, string $transcript): CallAutopsy
    {
        return $this->engine->coach($businessId, $sessionId, $transcript);
    }
}
