<?php

declare(strict_types=1);

namespace App\Modules\X118\Actions;

use App\Modules\X118\Models\OnboardingRun;

final class OnboardingConfirmAction
{
    public function handle(int $businessId, int $runId): array
    {
        $run = OnboardingRun::where('business_id', $businessId)->findOrFail($runId);
        $run->update(['status' => 'confirmed']);

        return [
            'run_id' => $run->id,
            'status' => 'confirmed',
        ];
    }
}
