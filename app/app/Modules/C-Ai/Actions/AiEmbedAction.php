<?php

declare(strict_types=1);

namespace App\Modules\CAi\Actions;

use App\Modules\CAi\Domain\AiEngine;

final class AiEmbedAction
{
    public function __construct(private readonly AiEngine $engine) {}

    public function handle(int $businessId, string $text, string $model = 'text-embedding-3-small'): array
    {
        return $this->engine->embed($businessId, $text, $model);
    }
}
