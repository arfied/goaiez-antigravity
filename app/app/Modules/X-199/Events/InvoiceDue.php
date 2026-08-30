<?php

declare(strict_types=1);

namespace App\Modules\X199\Events;

final class InvoiceDue
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $invoiceId,
        public readonly string $dueDate
    ) {}
}
