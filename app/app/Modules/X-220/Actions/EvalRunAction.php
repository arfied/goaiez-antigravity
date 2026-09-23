<?php

declare(strict_types=1);

namespace App\Modules\X220\Actions;

use App\Modules\X220\Models\AiPrompt;
use App\Modules\X220\Models\GoldenSet;

final class EvalRunAction
{
    public function handle(int $businessId, int $promptId, ?int $goldenSetId = null): array
    {
        $prompt = AiPrompt::where('business_id', $businessId)->findOrFail($promptId);

        $golden = $goldenSetId
            ? GoldenSet::where('business_id', $businessId)->findOrFail($goldenSetId)
            : GoldenSet::where('business_id', $businessId)->where('prompt_id', $promptId)->first();

        $threshold = $golden ? $golden->score_threshold : 90;
        $testCases = $golden ? ($golden->test_cases ?? []) : [];
        $totalCases = count($testCases);

        return [
            'status' => 'not_run',
            'reason' => 'no_evaluator',
            'test_cases_count' => $totalCases,
        ];
    }
}
