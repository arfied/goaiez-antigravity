<?php

declare(strict_types=1);

namespace App\Modules\X198\Events;

final class ChargebackReceived
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $paymentId,
        public readonly int $disputeAmountCents
    ) {}
}
