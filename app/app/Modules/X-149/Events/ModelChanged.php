<?php

declare(strict_types=1);

namespace App\Modules\X149\Events;

final class ModelChanged
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $modelName
    ) {}
}
