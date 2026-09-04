<?php

declare(strict_types=1);

namespace App\Modules\X199\Events;

final class InvoiceOverdue
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $invoiceId,
        public readonly int $daysOverdue
    ) {}
}
