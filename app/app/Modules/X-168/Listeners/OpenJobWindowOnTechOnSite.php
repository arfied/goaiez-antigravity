<?php

declare(strict_types=1);

namespace App\Modules\X168\Listeners;

use App\Modules\X168\Actions\TimesheetComputeAction;
use App\Modules\X171\Events\TechOnSite;
use Illuminate\Support\Carbon;

class OpenJobWindowOnTechOnSite
{
    public function handle(TechOnSite $event): void
    {
        $action = new TimesheetComputeAction;
        $action->recordJobWindow(
            businessId: $event->businessId,
            personId: $event->techId,
            jobId: $event->jobId,
            stateWindow: 'on_site',
            startedAt: Carbon::instance($event->occurredAt),
        );
    }
}
