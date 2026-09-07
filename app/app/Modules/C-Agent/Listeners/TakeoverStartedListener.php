<?php

declare(strict_types=1);

namespace App\Modules\CAgent\Listeners;

use App\Modules\CAgent\Models\TakeoverLatch;
use App\Modules\X01\Events\TakeoverStarted;

final class TakeoverStartedListener
{
    public function handle(TakeoverStarted $event): void
    {
        TakeoverLatch::updateOrCreate(
            ['business_id' => $event->businessId, 'conversation_id' => $event->conversationId],
            ['is_active' => true]
        );
    }
}
