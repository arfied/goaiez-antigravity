<?php

declare(strict_types=1);

namespace App\Modules\X194\Actions;

use App\Modules\X194\Events\ReportSent;
use App\Modules\X194\Models\ViewSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;

final class ViewScheduleAction
{
    /**
     * Sends scheduled report digest.
     * A digest with ZERO activity is NOT sent (TEST ANCHOR).
     */
    public function sendDigest(
        int $businessId,
        int $scheduleId,
        int $activityCount
    ): array {
        $schedule = ViewSchedule::where('business_id', $businessId)->findOrFail($scheduleId);

        // Guard: A digest with zero activity is not sent (TEST ANCHOR)
        if ($activityCount === 0) {
            return [
                'status' => 'skipped_zero_activity',
                'message' => 'Digest with zero activity is not sent',
                'schedule_id' => $schedule->id,
                'sent' => false,
            ];
        }

        $schedule->update(['last_sent_at' => Carbon::now()]);

        Event::dispatch(new ReportSent($businessId, $schedule->id, $activityCount));

        return [
            'status' => 'sent',
            'schedule_id' => $schedule->id,
            'activity_count' => $activityCount,
            'sent' => true,
        ];
    }
}
