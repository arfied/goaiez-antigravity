<?php

declare(strict_types=1);

namespace App\Modules\X219\Events;

final class ModelResolved
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $targetModule,
        public readonly string $modelName,
        public readonly bool $isFallback
    ) {}
}
