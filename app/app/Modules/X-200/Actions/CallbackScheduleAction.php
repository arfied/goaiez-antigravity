<?php

declare(strict_types=1);

namespace App\Modules\X200\Actions;

use App\Modules\X200\Models\CallSchedule;
use DateTimeInterface;

final class CallbackScheduleAction
{
    public function scheduleCallback(int $businessId, string $phone, DateTimeInterface $scheduledAt): CallSchedule
    {
        return CallSchedule::create([
            'business_id' => $businessId,
            'phone' => $phone,
            'scheduled_at' => $scheduledAt,
            'is_completed' => false,
        ]);
    }
}
