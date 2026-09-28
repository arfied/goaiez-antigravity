<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\AiTask;

final readonly class ImageRequest
{
    public function __construct(
        public AiTask $task,
        public string $prompt,
        public string $quality = 'medium',
    ) {}
}
