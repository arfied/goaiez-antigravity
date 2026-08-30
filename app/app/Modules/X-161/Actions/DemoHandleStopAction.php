<?php

declare(strict_types=1);

namespace App\Modules\X161\Actions;

use App\Modules\X161\Events\DemoStopReceived;
use Illuminate\Support\Facades\Event;

final class DemoHandleStopAction
{
    public function handleStop(int $businessId, int $demoTenantId, string $channel = 'sms'): void
    {
        Event::dispatch(new DemoStopReceived($businessId, $demoTenantId, $channel));
    }
}
