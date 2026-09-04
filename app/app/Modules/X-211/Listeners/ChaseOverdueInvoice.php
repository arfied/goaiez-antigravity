<?php

declare(strict_types=1);

namespace App\Modules\X211\Listeners;

use App\Modules\X199\Events\InvoiceOverdue;
use App\Modules\X211\Domain\ArEngine;

final class ChaseOverdueInvoice
{
    public function __construct(private readonly ArEngine $engine) {}

    public function handle(InvoiceOverdue $event): void
    {
        $this->engine->chaseOverdue($event->businessId, $event->invoiceId, $event->daysOverdue);
    }
}
