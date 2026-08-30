<?php

declare(strict_types=1);

namespace App\Modules\X127\Actions;

use App\Modules\X127\Events\TenantzeroClaimVerified;
use App\Modules\X127\Models\PublishedMetric;
use Illuminate\Support\Facades\Event;

final class TenantzeroProofAction
{
    /**
     * Recomputes published claim from live query (TEST ANCHOR).
     * Must match to the digit — a drifted claim is pulled automatically, not just flagged!
     */
    public function handle(int $businessId, string $metricKey, string $liveRecomputedValue): array
    {
        $metric = PublishedMetric::where('business_id', $businessId)->where('metric_key', $metricKey)->firstOrFail();

        // 1. Check if published value matches recomputed live value to the exact digit (TEST ANCHOR)
        if ($metric->published_value === $liveRecomputedValue) {
            $metric->update([
                'status' => 'verified',
                'last_verified_value' => $liveRecomputedValue,
            ]);

            Event::dispatch(new TenantzeroClaimVerified($businessId, $metricKey, $liveRecomputedValue));

            return [
                'status' => 'verified',
                'metric_key' => $metricKey,
                'value' => $liveRecomputedValue,
            ];
        }

        // 2. Drift detected: PULL CLAIM AUTOMATICALLY (published_value = null, status = 'pulled_drifted') (TEST ANCHOR)
        $previousClaim = $metric->published_value;
        $metric->update([
            'status' => 'pulled_drifted',
            'published_value' => null, // Claim pulled from publication
            'last_verified_value' => $liveRecomputedValue,
        ]);

        return [
            'status' => 'pulled_drifted',
            'metric_key' => $metricKey,
            'previous_claim' => $previousClaim,
            'live_value' => $liveRecomputedValue,
            'message' => 'Claim drifted and was pulled automatically from publication',
        ];
    }
}
