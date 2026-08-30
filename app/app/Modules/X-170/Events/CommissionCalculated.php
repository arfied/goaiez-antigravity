<?php

declare(strict_types=1);

namespace App\Modules\X170\Events;

final class CommissionCalculated
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $commissionId,
        public readonly int $amountCents
    ) {}
}
