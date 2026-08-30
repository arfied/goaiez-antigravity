<?php

declare(strict_types=1);

namespace App\Modules\X145\Actions;

use App\Modules\X145\Models\Decision;
use App\Modules\X145\Models\DecisionOutcome;
use Carbon\Carbon;

final class DecisionGradeOutcomeAction
{
    /**
     * A proposal is graded by its outcome event within the window, either way (TEST ANCHOR).
     */
    public function grade(int $businessId, int $decisionId, string $outcomeEvent, bool $isFavorable): DecisionOutcome
    {
        $decision = Decision::where('business_id', $businessId)->findOrFail($decisionId);

        return DecisionOutcome::create([
            'business_id' => $businessId,
            'decision_id' => $decision->id,
            'outcome_event' => $outcomeEvent,
            'is_favorable' => $isFavorable,
            'graded_at' => Carbon::now(),
        ]);
    }
}
