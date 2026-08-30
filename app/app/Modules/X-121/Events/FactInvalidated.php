<?php

declare(strict_types=1);

namespace App\Modules\X121\Events;

final class FactInvalidated
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $factId,
        public readonly string $key,
        public readonly string $commitId,
    ) {}
}
