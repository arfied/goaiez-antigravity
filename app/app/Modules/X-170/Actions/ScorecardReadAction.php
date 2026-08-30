<?php

declare(strict_types=1);

namespace App\Modules\X170\Actions;

use App\Modules\X170\Models\Scorecard;

final class ScorecardReadAction
{
    public function handle(int $businessId, int $staffId, string $periodKey = '2026-Q3'): Scorecard
    {
        return Scorecard::firstOrCreate(
            ['business_id' => $businessId, 'staff_id' => $staffId, 'period_key' => $periodKey],
            ['revenue_collected_cents' => 0, 'commissions_earned_cents' => 0, 'average_review_score' => 5.00]
        );
    }
}
