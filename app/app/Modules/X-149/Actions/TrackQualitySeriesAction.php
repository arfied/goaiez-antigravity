<?php

declare(strict_types=1);

namespace App\Modules\X149\Actions;

use App\Modules\X149\Models\QualitySeries;

final class TrackQualitySeriesAction
{
    /**
     * Track refusal rate: a 40% drop over 24h raises refusal_rate.fell before any human looks (TEST ANCHOR).
     */
    public function record(
        int $businessId,
        float $currentRefusalRate,
        float $baselineRefusalRate
    ): QualitySeries {
        $dropPct = 0.0;
        if ($baselineRefusalRate > 0) {
            $dropPct = ($baselineRefusalRate - $currentRefusalRate) / $baselineRefusalRate;
        }

        $anomaly = false;
        $eventName = null;

        if ($dropPct >= 0.40) { // 40% drop
            $anomaly = true;
            $eventName = 'refusal_rate.fell'; // TEST ANCHOR
        }

        return QualitySeries::create([
            'business_id' => $businessId,
            'refusal_rate' => $currentRefusalRate,
            'refusal_rate_drop_pct' => $dropPct,
            'anomaly_detected' => $anomaly,
            'event_name' => $eventName,
        ]);
    }
}
