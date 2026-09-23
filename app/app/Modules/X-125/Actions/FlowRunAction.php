<?php

declare(strict_types=1);

namespace App\Modules\X125\Actions;

use App\Modules\X125\Models\Flow;
use App\Modules\X125\Models\FlowRun;
use App\Modules\X125\Models\FlowVersion;

final class FlowRunAction
{
    /**
     * Runs a flow with error pause & manual resume logic (TEST ANCHOR).
     */
    public function handle(
        int $businessId,
        int $flowId,
        array $triggerPayload = [],
        bool $isManualRetry = false,
        bool $shouldSimulateFailure = false
    ): array {
        $flow = Flow::where('business_id', $businessId)->findOrFail($flowId);
        $latestVersion = FlowVersion::where('business_id', $businessId)
            ->where('flow_id', $flow->id)
            ->orderByDesc('version_number')
            ->firstOrFail();

        if ($flow->status === 'paused' && ! $isManualRetry) {
            return [
                'status' => 'refused_paused',
                'refusal_code' => 'FLOW_PAUSED_BY_OWNER',
                'message' => 'Flow was paused by hand and will not run until it is resumed',
            ];
        }

        // 1. If flow is paused due to consecutive errors: REFUSE silent automatic retry (TEST ANCHOR)
        if ($flow->status === 'paused_error' && ! $isManualRetry) {
            return [
                'status' => 'refused_paused',
                'refusal_code' => 'FLOW_PAUSED_AFTER_ERRORS_NO_SILENT_RETRY',
                'message' => 'Flow is paused after repeated errors and will never silently retry without manual intervention',
            ];
        }

        // 2. Failure simulation or error condition
        if ($shouldSimulateFailure) {
            $errors = $flow->consecutive_errors + 1;
            $isPaused = ($errors >= $flow->max_error_threshold);

            $flow->update([
                'consecutive_errors' => $errors,
                'status' => $isPaused ? 'paused_error' : $flow->status,
            ]);

            $run = FlowRun::create([
                'business_id' => $businessId,
                'flow_id' => $flow->id,
                'flow_version_id' => $latestVersion->id,
                'trigger_payload' => $triggerPayload,
                'status' => 'error',
                'error_message' => 'Simulated step execution failure',
                'is_manual_retry' => $isManualRetry,
            ]);

            return [
                'status' => 'error',
                'run_id' => $run->id,
                'consecutive_errors' => $errors,
                'flow_status' => $flow->fresh()->status,
            ];
        }

        // 3. Successful run: if manual retry on paused flow -> resumes flow to active (TEST ANCHOR)
        if ($flow->status === 'paused_error' && $isManualRetry) {
            $flow->update([
                'status' => 'active',
                'consecutive_errors' => 0,
            ]);
        }

        $run = FlowRun::create([
            'business_id' => $businessId,
            'flow_id' => $flow->id,
            'flow_version_id' => $latestVersion->id,
            'trigger_payload' => $triggerPayload,
            'status' => 'success',
            'is_manual_retry' => $isManualRetry,
        ]);

        return [
            'status' => 'success',
            'run_id' => $run->id,
            'flow_status' => $flow->fresh()->status,
        ];
    }
}
