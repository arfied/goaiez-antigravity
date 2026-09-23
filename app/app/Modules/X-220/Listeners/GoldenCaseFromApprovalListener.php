<?php

declare(strict_types=1);

namespace App\Modules\X220\Listeners;

use App\Events\ReplyApproved;
use App\Modules\X220\Models\GoldenSet;
use App\Services\Config\DefaultsRegistry;

final class GoldenCaseFromApprovalListener
{
    public function __construct(
        private readonly DefaultsRegistry $registry,
    ) {}

    public function handle(ReplyApproved $event): void
    {
        if ($event->promptId === null) {
            return;
        }

        $threshold = $this->registry->int('ai.eval.pass_threshold_pct');
        $maxCases = $this->registry->int('ai.eval.max_cases_per_set');

        $set = GoldenSet::firstOrCreate([
            'business_id' => $event->businessId,
            'prompt_id' => (int) $event->promptId,
            'prompt_version' => $event->promptVersion !== null ? (int) $event->promptVersion : null,
        ], [
            'test_cases' => [],
            'expected_outputs' => [],
            'score_threshold' => $threshold,
        ]);

        $testCases = $set->test_cases ?? [];
        $expectedOutputs = $set->expected_outputs ?? [];

        $testCases[] = ['input' => $event->reviewText];
        $expectedOutputs[] = ['expected' => $event->approvedText];

        if (count($testCases) > $maxCases) {
            $testCases = array_slice($testCases, -$maxCases);
            $expectedOutputs = array_slice($expectedOutputs, -$maxCases);
        }

        $set->test_cases = array_values($testCases);
        $set->expected_outputs = array_values($expectedOutputs);
        $set->save();
    }
}
