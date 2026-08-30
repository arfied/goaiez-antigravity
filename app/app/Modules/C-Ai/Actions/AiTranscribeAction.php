<?php

declare(strict_types=1);

namespace App\Modules\CAi\Actions;

use App\Modules\CAi\Domain\AiEngine;

final class AiTranscribeAction
{
    public function __construct(private readonly AiEngine $engine) {}

    public function handle(int $businessId, string $audioPath, string $model = 'whisper-1'): array
    {
        return $this->engine->transcribe($businessId, $audioPath, $model);
    }
}
