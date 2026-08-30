<?php

declare(strict_types=1);

namespace App\Modules\X219\Events;

final class ModelFallback
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $targetModule,
        public readonly string $primaryModel,
        public readonly string $fallbackModel,
        public readonly string $reason
    ) {}
}
