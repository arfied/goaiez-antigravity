<?php

declare(strict_types=1);

namespace App\Modules\X211\Listeners;

use App\Modules\X211\Events\ArEscalatedToHuman;
use App\Modules\X211\Events\ArOverdue;
use App\Modules\X211\Models\ArDunningAction;
use App\Support\Tenancy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;

final class ProcessOverdueReceivable implements ShouldQueue
{
    public function handle(ArOverdue $event): void
    {
        Tenancy::actingAs((int) $event->businessId, function () use ($event) {
            // R211: resolution precedes any automatic stop
            $action = ArDunningAction::firstOrCreate([
                'business_id' => $event->businessId,
                'invoice_id' => $event->invoiceId,
                'action' => 'escalate_to_human',
            ], [
                'reason' => 'Overdue and waiting on a person: nobody has recorded why this invoice is unpaid, and nothing is stopped until someone does.',
            ]);

            if ($action->wasRecentlyCreated) {
                Event::dispatch(new ArEscalatedToHuman($event->businessId, $event->invoiceId, $action->reason));
            }
        });
    }
}
