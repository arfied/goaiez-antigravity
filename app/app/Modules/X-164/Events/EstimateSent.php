<?php

declare(strict_types=1);

namespace App\Modules\X164\Events;

final class EstimateSent
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $estimateId,
        public readonly string $estimateNumber,
        public readonly int $totalCents
    ) {}
}
