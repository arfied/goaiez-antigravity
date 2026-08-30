<?php

declare(strict_types=1);

namespace App\Modules\X184\Actions;

use App\Modules\X184\Models\ContentPlan;

final class PlanApproveCadenceAction
{
    /**
     * Approves weekly publishing cadence in <=3 taps (TEST ANCHOR & G5-49, G16-16).
     */
    public function approveCadence(int $businessId, int $planId): ContentPlan
    {
        $plan = ContentPlan::where('business_id', $businessId)->findOrFail($planId);

        $plan->update([
            'is_cadence_approved' => true,
        ]);

        return $plan;
    }
}
