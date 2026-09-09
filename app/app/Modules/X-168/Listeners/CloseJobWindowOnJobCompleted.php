<?php

declare(strict_types=1);

namespace App\Modules\X168\Listeners;

use App\Modules\X168\Actions\TimesheetComputeAction;
use App\Modules\X171\Events\JobCompleted;
use Illuminate\Support\Carbon;

class CloseJobWindowOnJobCompleted
{
    public function handle(JobCompleted $event): void
    {
        if ($event->occurredAt === null) {
            return;
        }

        $action = new TimesheetComputeAction;
        $action->closeJobWindow($event->businessId, $event->jobId, Carbon::instance($event->occurredAt));
    }
}
