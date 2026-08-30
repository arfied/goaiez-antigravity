<?php

declare(strict_types=1);

namespace App\Modules\X07\Actions;

use App\Modules\X07\Models\Forecast;

final class ChurnScoreAction
{
    /**
     * Evaluates churn risk.
     * A risk row above threshold yields exactly one alert row (TEST ANCHOR).
     */
    public function evaluateRisk(int $businessId, string $periodMonth, int $riskScore, int $threshold = 70): array
    {
        $isHighRisk = ($riskScore >= $threshold);
        $alertGenerated = $isHighRisk;

        $forecast = Forecast::updateOrCreate(
            ['business_id' => $businessId, 'period_month' => $periodMonth],
            [
                'churn_risk_pct' => $riskScore,
                'is_high_risk' => $isHighRisk,
                'alert_created' => $alertGenerated,
            ]
        );

        return [
            'forecast_id' => $forecast->id,
            'period_month' => $periodMonth,
            'risk_score' => $riskScore,
            'is_high_risk' => $isHighRisk,
            'alert_yielded' => $alertGenerated ? 1 : 0,
        ];
    }
}
