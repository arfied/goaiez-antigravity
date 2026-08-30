<?php

declare(strict_types=1);

namespace App\Modules\X198\Events;

final class PayoutReconciled
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $payoutId,
        public readonly int $amountCents
    ) {}
}
