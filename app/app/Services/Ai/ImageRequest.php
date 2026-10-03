<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\AiModel;
use App\Enums\AiTask;

final readonly class ImageRequest
{
    public function __construct(
        public AiTask $task,
        public string $prompt,
        public string $quality = 'medium',
        // Null means the task's setting decides; SiteImageGenerateAction names FLUX once a fal.ai key exists.
        public ?AiModel $model = null,
    ) {}
}
