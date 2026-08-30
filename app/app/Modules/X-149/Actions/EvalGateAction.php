<?php

declare(strict_types=1);

namespace App\Modules\X149\Actions;

use App\Modules\X149\Events\PromptChanged;
use App\Modules\X149\Models\EvalRun;
use Illuminate\Support\Facades\Event;

final class EvalGateAction
{
    /**
     * Evaluates gate deployment for prompt version (TEST ANCHOR).
     */
    public function handle(int $businessId, string $promptVersion): array
    {
        $failedRuns = EvalRun::where('business_id', $businessId)
            ->where('prompt_version', $promptVersion)
            ->where('sample_price_hallucinated', true)
            ->get();

        // 1. A prompt change that makes the agent answer a SAMPLE price fails the gate and CANNOT deploy (TEST ANCHOR)
        if ($failedRuns->isNotEmpty()) {
            return [
                'status' => 'gate_failed',
                'can_deploy' => false,
                'refusal_code' => 'SAMPLE_PRICE_HALLUCINATION_DETECTED',
                'message' => 'A prompt change that makes the agent answer a SAMPLE price fails the gate and cannot deploy',
            ];
        }

        Event::dispatch(new PromptChanged($businessId, $promptVersion));

        return [
            'status' => 'gate_passed',
            'can_deploy' => true,
            'prompt_version' => $promptVersion,
        ];
    }
}
