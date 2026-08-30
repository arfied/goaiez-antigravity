<?php

declare(strict_types=1);

namespace App\Modules\X119\Events;

final class FactCreated
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $factId,
        public readonly string $key,
        public readonly string $value,
        public readonly string $source
    ) {}
}
