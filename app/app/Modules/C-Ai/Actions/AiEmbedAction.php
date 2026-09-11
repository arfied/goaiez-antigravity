<?php

declare(strict_types=1);

namespace App\Modules\CAi\Actions;

use App\Enums\AiModel;
use App\Modules\CAi\Domain\AiEngine;

final class AiEmbedAction
{
    public function __construct(private readonly AiEngine $engine) {}

    public function handle(int $businessId, string $text, ?string $model = null): array
    {
        $model ??= AiModel::TextEmbedding3Small->apiModelId();

        return $this->engine->embed($businessId, $text, $model);
    }
}
