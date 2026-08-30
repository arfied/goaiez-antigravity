<?php

declare(strict_types=1);

namespace App\Modules\X162\Actions;

use App\Modules\X162\Events\EtaUpdated;
use App\Modules\X162\Models\EtaPrediction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;

final class EtaUpdateAction
{
    /**
     * Updates ETA: triggers new customer message on ETA change over threshold minutes (TEST ANCHOR).
     */
    public function updateEta(int $businessId, int $jobId, int $newEtaMinutes, int $thresholdDeltaMinutes = 10): array
    {
        $previousEta = EtaPrediction::where('business_id', $businessId)->where('job_id', $jobId)->latest('id')->first();
        $oldMinutes = $previousEta ? $previousEta->eta_minutes : $newEtaMinutes;
        $delta = abs($newEtaMinutes - $oldMinutes);

        $now = Carbon::now();
        $notificationResent = ($delta >= $thresholdDeltaMinutes);

        $newPrediction = EtaPrediction::create([
            'business_id' => $businessId,
            'job_id' => $jobId,
            'estimated_arrival_at' => $now->copy()->addMinutes($newEtaMinutes),
            'eta_minutes' => $newEtaMinutes,
            'notification_sent_at' => $notificationResent ? $now : null,
        ]);

        if ($notificationResent) {
            Event::dispatch(new EtaUpdated($businessId, $jobId, $newEtaMinutes, $delta));
        }

        return [
            'status' => 'updated',
            'job_id' => $jobId,
            'new_eta_minutes' => $newEtaMinutes,
            'delta_minutes' => $delta,
            'notification_resent' => $notificationResent,
        ];
    }
}
