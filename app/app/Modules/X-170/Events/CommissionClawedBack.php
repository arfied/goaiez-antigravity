<?php

declare(strict_types=1);

namespace App\Modules\X170\Events;

final class CommissionClawedBack
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $commissionId,
        public readonly int $clawbackAmountCents,
        public readonly string $reason
    ) {}
}
