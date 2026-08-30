<?php

declare(strict_types=1);

namespace App\Modules\X164\Events;

final class EstimateExpired
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $estimateId
    ) {}
}
