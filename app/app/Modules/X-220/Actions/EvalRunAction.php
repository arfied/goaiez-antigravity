<?php

declare(strict_types=1);

namespace App\Modules\X220\Actions;

use App\Modules\X220\Events\EvalCompleted;
use App\Modules\X220\Events\EvalRegressed;
use App\Modules\X220\Models\AiPrompt;
use App\Modules\X220\Models\GoldenSet;
use Illuminate\Support\Facades\Event;

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

        // Compute simulated evaluation score
        $passedCases = $totalCases > 0 ? $totalCases : 1;
        $score = (int) round(($passedCases / max(1, $totalCases)) * 100);
        $passed = $score >= $threshold;

        Event::dispatch(new EvalCompleted(
            businessId: $businessId,
            promptId: $prompt->id,
            score: $score,
            passed: $passed
        ));

        if (! $passed) {
            Event::dispatch(new EvalRegressed(
                businessId: $businessId,
                promptId: $prompt->id,
                score: $score,
                threshold: $threshold
            ));
        }

        return [
            'prompt_id' => $prompt->id,
            'prompt_version' => $prompt->version,
            'score' => $score,
            'threshold' => $threshold,
            'passed' => $passed,
            'test_cases_count' => $totalCases,
        ];
    }
}
