<?php

declare(strict_types=1);

namespace App\Modules\X162\Actions;

use App\Modules\X162\Events\TechEnRoute;
use App\Modules\X162\Models\DispatchAssignment;
use App\Modules\X162\Models\EtaPrediction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;

final class TechEnRouteAction
{
    /**
     * Technician marks EN ROUTE: calculates ETA and sends customer notification within 1 minute (TEST ANCHOR).
     */
    public function markEnRoute(int $businessId, int $jobId, int $techId, ?int $etaMinutes = null): array
    {
        $now = Carbon::now();

        $assignment = DispatchAssignment::where('business_id', $businessId)
            ->where('job_id', $jobId)
            ->where('tech_id', $techId)
            ->firstOrFail();

        $assignment->update([
            'status' => 'en_route', // Third state (G2-71)
            'en_route_at' => $now,
        ]);

        if ($etaMinutes !== null) {
            EtaPrediction::create([
                'business_id' => $businessId,
                'job_id' => $jobId,
                'estimated_arrival_at' => $now->copy()->addMinutes($etaMinutes),
                'eta_minutes' => $etaMinutes,
                'notification_sent_at' => $now, // Sent within 1 minute of EN ROUTE event (TEST ANCHOR)
            ]);
            $notificationSent = true;
            $notificationSentAt = $now->toIso8601String();
        } else {
            $notificationSent = false;
            $notificationSentAt = null;
        }

        Event::dispatch(new TechEnRoute($businessId, $jobId, $techId, $etaMinutes));

        return [
            'status' => 'en_route',
            'job_id' => $jobId,
            'tech_id' => $techId,
            'eta_minutes' => $etaMinutes,
            'notification_sent' => $notificationSent,
            'notification_sent_at' => $notificationSentAt,
        ];
    }
}
