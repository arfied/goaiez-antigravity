<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X118\Models\OnboardingRun;
use App\Modules\X118\Models\OnboardingStep;

class X118Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-118';
    }

    public function fill(Business $business): int
    {
        if (OnboardingRun::where('business_id', $business->id)->where('business_name', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $run1 = OnboardingRun::create([
            'business_id' => $business->id,
            'business_name' => self::MARKER.'Ridgeline HVAC',
            'contact_phone' => '+15550100001',
            'provisioned_number' => '+15550200001',
            'status' => 'live',
            'ttfm_ms' => 950,
            'asked_fields_count' => 2,
        ]);

        OnboardingStep::create([
            'business_id' => $business->id,
            'run_id' => $run1->id,
            'step_name' => 'demo·business name confirmed',
            'is_hard_stop' => false,
            'status' => 'completed',
        ]);

        OnboardingStep::create([
            'business_id' => $business->id,
            'run_id' => $run1->id,
            'step_name' => 'demo·phone number verified',
            'is_hard_stop' => false,
            'status' => 'completed',
        ]);

        OnboardingStep::create([
            'business_id' => $business->id,
            'run_id' => $run1->id,
            'step_name' => 'demo·industry inferred',
            'is_hard_stop' => false,
            'status' => 'completed',
        ]);

        OnboardingRun::create([
            'business_id' => $business->id,
            'business_name' => self::MARKER.'Fairview Electric',
            'contact_phone' => '+15550100002',
            'provisioned_number' => '+15550200002',
            'status' => 'confirmed',
            'ttfm_ms' => 1400,
            'asked_fields_count' => 2,
        ]);

        return 5;
    }

    public function purge(Business $business): int
    {
        $runs = OnboardingRun::where('business_id', $business->id)
            ->where('business_name', 'like', self::MARKER.'%')
            ->pluck('id');

        if ($runs->isEmpty()) {
            return 0;
        }

        $deletedSteps = OnboardingStep::whereIn('run_id', $runs)->delete();
        $deletedRuns = OnboardingRun::whereIn('id', $runs)->delete();

        return $deletedSteps + $deletedRuns;
    }
}
