<?php

declare(strict_types=1);

namespace App\Modules\X147\Events;

final class RcsSent
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $recipientPhone,
        public readonly float $billedRate
    ) {}
}
