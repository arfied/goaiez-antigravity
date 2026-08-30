<?php

declare(strict_types=1);

namespace App\Modules\X128\Events;

final class SeamViolated
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $moduleA,
        public readonly string $moduleB,
        public readonly string $violation
    ) {}
}
