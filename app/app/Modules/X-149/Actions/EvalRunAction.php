<?php

declare(strict_types=1);

namespace App\Modules\X149\Actions;

use App\Modules\X149\Models\EvalRun;
use App\Modules\X149\Models\EvalSet;

final class EvalRunAction
{
    public function handle(
        int $businessId,
        int $evalSetId,
        string $promptVersion,
        bool $leaksSamplePrice = false
    ): EvalRun {
        $evalSet = EvalSet::where('business_id', $businessId)->findOrFail($evalSetId);

        $passed = ! $leaksSamplePrice;
        $failureReason = $leaksSamplePrice ? 'Agent answered hallucinated sample price' : null;

        return EvalRun::create([
            'business_id' => $businessId,
            'eval_set_id' => $evalSet->id,
            'prompt_version' => $promptVersion,
            'passed' => $passed,
            'failure_reason' => $failureReason,
            'sample_price_hallucinated' => $leaksSamplePrice,
        ]);
    }
}
