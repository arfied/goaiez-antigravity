<?php

declare(strict_types=1);

namespace App\Modules\CAi\Actions;

use App\Modules\CAi\Domain\AiEngine;

final class AiSpeakAction
{
    public function __construct(private readonly AiEngine $engine) {}

    public function handle(int $businessId, string $text, string $voice = 'alloy'): array
    {
        return $this->engine->speak($businessId, $text, $voice);
    }
}
