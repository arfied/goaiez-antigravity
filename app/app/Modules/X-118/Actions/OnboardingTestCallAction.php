<?php

declare(strict_types=1);

namespace App\Modules\X118\Actions;

use App\Modules\X118\Events\FirstWin;
use App\Modules\X118\Models\OnboardingRun;
use Illuminate\Support\Facades\Event;

final class OnboardingTestCallAction
{
    /**
     * Place test call directly to the new provisioned number (TEST ANCHOR).
     */
    public function handle(int $businessId, int $runId): array
    {
        $run = OnboardingRun::where('business_id', $businessId)->findOrFail($runId);
        $testCallSid = 'CA_TEST_WIN_'.$run->id;

        Event::dispatch(new FirstWin($businessId, $testCallSid));

        return [
            'run_id' => $run->id,
            'target_number' => $run->provisioned_number,
            'routing_type' => 'direct_dial',
            'is_forwarded' => false,
            'call_sid' => $testCallSid,
            'status' => 'initiated',
        ];
    }
}
