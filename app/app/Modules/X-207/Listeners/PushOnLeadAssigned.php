<?php

declare(strict_types=1);

namespace App\Modules\X207\Listeners;

use App\Modules\X10\Events\LeadAssigned;
use App\Modules\X207\Jobs\SendPushToUserJob;

final class PushOnLeadAssigned
{
    public function handle(LeadAssigned $event): void
    {
        SendPushToUserJob::dispatch(
            $event->businessId,
            $event->staffId,
            'lead_assigned',
            '/account/customers'
        );
    }
}
