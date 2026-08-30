<?php

declare(strict_types=1);

namespace App\Modules\X173\Events;

final class CategoryInferred
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $transactionRef,
        public readonly string $assignedCategory,
        public readonly float $confidenceScore
    ) {}
}
