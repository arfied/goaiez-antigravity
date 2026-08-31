<?php

declare(strict_types=1);

namespace App\Modules\X198\Events;

final class PaymentCaptured
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $paymentId,
        public readonly ?string $gatewayChargeId = null,
        public readonly int $amountCents = 0
    ) {}
}
