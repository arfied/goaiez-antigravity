<?php

declare(strict_types=1);

namespace App\Modules\X199\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class OverflowReversed
{
    use Dispatchable;

    public function __construct(
        public readonly int $businessId,
        public readonly int $customerId,
        public readonly int $invoiceId,
        public readonly int $amountCents,
    ) {}
}
