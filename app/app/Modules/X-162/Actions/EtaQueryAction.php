<?php

declare(strict_types=1);

namespace App\Modules\X162\Actions;

use App\Modules\X162\Models\DispatchAssignment;
use App\Modules\X162\Models\EtaPrediction;

final class EtaQueryAction
{
    /**
     * Answers "where is he?": cites current state and ETA, never a guess (TEST ANCHOR).
     */
    public function handle(int $businessId, int $jobId): array
    {
        $assignment = DispatchAssignment::where('business_id', $businessId)->where('job_id', $jobId)->first();
        $eta = EtaPrediction::where('business_id', $businessId)->where('job_id', $jobId)->latest('id')->first();

        $currentState = $assignment ? $assignment->status : 'unassigned';
        $etaMinutes = $eta ? $eta->eta_minutes : null;
        $arrivalAt = $eta ? $eta->estimated_arrival_at->toIso8601String() : null;

        return [
            'job_id' => $jobId,
            'current_state' => $currentState, // Cites exact state (TEST ANCHOR)
            'eta_minutes' => $etaMinutes,      // Cites exact ETA (TEST ANCHOR)
            'estimated_arrival_at' => $arrivalAt,
            'is_guess' => false,
            'grounded_answer' => $assignment && $eta
                ? "Technician is currently {$currentState} with an ETA of {$etaMinutes} minutes."
                : "Job is currently {$currentState}.",
        ];
    }
}
