<?php

declare(strict_types=1);

namespace App\Modules\X201\Events;

final class DisputeOpened
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $disputeId,
        public readonly int $invoiceId,
        public readonly int $amountCents
    ) {}
}
