<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Events;

final class RefundIssued
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $refundAmountHundredthsCents,
        public readonly string $reason
    ) {}
}
