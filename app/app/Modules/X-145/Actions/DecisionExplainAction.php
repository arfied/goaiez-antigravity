<?php

declare(strict_types=1);

namespace App\Modules\X145\Actions;

use App\Modules\X145\Models\Decision;

final class DecisionExplainAction
{
    public function explain(int $businessId, int $decisionId): array
    {
        $decision = Decision::where('business_id', $businessId)->findOrFail($decisionId);

        return [
            'decision_id' => $decision->id,
            'proposed_action' => $decision->proposed_action,
            'explanation' => $decision->explanation,
            'has_explanation' => ! empty($decision->explanation),
        ];
    }
}
