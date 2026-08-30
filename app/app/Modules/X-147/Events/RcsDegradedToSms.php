<?php

declare(strict_types=1);

namespace App\Modules\X147\Events;

final class RcsDegradedToSms
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $recipientPhone,
        public readonly float $billedRate,
        public readonly string $reason
    ) {}
}
