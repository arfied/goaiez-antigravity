<?php

declare(strict_types=1);

namespace App\Modules\X171\Actions;

use App\Modules\X171\Events\JobCompleted;
use App\Modules\X171\Events\TechOnSite;
use Illuminate\Support\Facades\Event;

final class JobStateAction
{
    /**
     * 1-Tap status change from app (TEST ANCHOR).
     */
    public function updateState(int $businessId, int $jobId, int $techId, string $newState, int $tapCount = 1): array
    {
        if ($newState === 'on_site') {
            Event::dispatch(new TechOnSite($businessId, $jobId, $techId));

            return [
                'status' => 'on_site',
                'tap_count' => $tapCount, // Exactly 1 tap (TEST ANCHOR)
                'job_id' => $jobId,
            ];
        }

        if ($newState === 'completed') {
            Event::dispatch(new JobCompleted($businessId, $jobId, $techId));

            return [
                'status' => 'completed',
                'tap_count' => $tapCount,
                'job_id' => $jobId,
            ];
        }

        return [
            'status' => $newState,
            'tap_count' => $tapCount,
            'job_id' => $jobId,
        ];
    }
}
