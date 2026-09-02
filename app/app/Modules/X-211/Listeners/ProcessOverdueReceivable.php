<?php

declare(strict_types=1);

namespace App\Modules\X211\Listeners;

use App\Modules\X211\Events\ArEscalatedToHuman;
use App\Modules\X211\Events\ArOverdue;
use App\Modules\X211\Models\ArDunningAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;

final class ProcessOverdueReceivable implements ShouldQueue
{
    public function handle(ArOverdue $event): void
    {
        // R211: resolution precedes any automatic stop
        $action = ArDunningAction::create([
            'business_id' => $event->businessId,
            'invoice_id' => $event->invoiceId,
            'action' => 'escalate_to_human',
            'reason' => 'R211: Overdue invoice requires human resolution attempt before any suspension.',
        ]);

        Event::dispatch(new ArEscalatedToHuman($event->businessId, $event->invoiceId, $action->reason));
    }
}
