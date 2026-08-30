<?php

declare(strict_types=1);

namespace App\Modules\X159\Actions;

use App\Modules\X159\Events\ExperientialTested;
use App\Modules\X159\Models\ExperientialTest;
use Illuminate\Support\Facades\Event;

final class AuditExperientialAction
{
    /**
     * Records mystery shopper experiential test results.
     * The experiential table has AT MOST ONE row per prospect per test type (TEST ANCHOR).
     */
    public function recordTest(
        int $businessId,
        int $prospectId,
        string $testType,
        string $resultSummary,
        bool $passed
    ): ExperientialTest {
        // TEST ANCHOR: updateOrCreate ensures at most one row per prospect per test type
        $test = ExperientialTest::updateOrCreate(
            ['business_id' => $businessId, 'prospect_id' => $prospectId, 'test_type' => $testType],
            [
                'result_summary' => $resultSummary,
                'passed' => $passed,
            ]
        );

        Event::dispatch(new ExperientialTested($businessId, $prospectId, $testType, $passed));

        return $test;
    }
}
