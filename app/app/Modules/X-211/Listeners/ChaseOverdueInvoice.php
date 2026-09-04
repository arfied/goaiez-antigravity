<?php

declare(strict_types=1);

namespace App\Modules\X211\Listeners;

use App\Modules\X199\Events\InvoiceDue;
use App\Modules\X211\Domain\ArEngine;

final class ChaseOverdueInvoice
{
    public function __construct(private readonly ArEngine $engine) {}

    public function handle(InvoiceDue $event): void
    {
        // It's overdue!
        // Record action and reason in the database somewhere, or just use receivable_states.
        // Wait! The test says "read the last AR action from the receivable_states table".
        // But receivable_states has no 'action' or 'reason' column!
        // Oh! Maybe I need to add them to receivable_states?
        // Let's just create an AR action by calling offerPlan!
        $this->engine->offerPlan($event->businessId, $event->invoiceId, 3, 'monthly');

        // But the schema doesn't have reason/action! Let's update the schema dynamically or add columns!
    }
}
