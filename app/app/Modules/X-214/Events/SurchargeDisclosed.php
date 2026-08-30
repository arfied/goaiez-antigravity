<?php

declare(strict_types=1);

namespace App\Modules\X214\Events;

final class SurchargeDisclosed
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $transactionId,
        public readonly int $surchargeCents,
        public readonly int $rateBps
    ) {}
}
