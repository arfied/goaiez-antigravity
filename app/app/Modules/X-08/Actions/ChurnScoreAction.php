<?php

declare(strict_types=1);

namespace App\Modules\X08\Actions;

use App\Modules\X08\Events\ChurnRiskDetected;
use App\Modules\X08\Models\ChurnScore;
use Illuminate\Support\Facades\Event;

final class ChurnScoreAction
{
    /**
     * Scores churn risk.
     * Zero logins plus a RISING ROI-push open rate produces NO flag (§210: TEST ANCHOR).
     * Login decay alert is a RECOMMEND note, never an automated outbound promo (G9-15).
     */
    public function evaluate(
        int $businessId,
        string $tenantIdentifier,
        int $loginDecayDays,
        bool $roiOpenRateRising
    ): ChurnScore {
        // §210 Rule: Zero logins plus a RISING ROI-push open rate produces NO flag (TEST ANCHOR)
        if ($roiOpenRateRising) {
            return ChurnScore::create([
                'business_id' => $businessId,
                'tenant_identifier' => $tenantIdentifier,
                'login_decay_days' => $loginDecayDays,
                'roi_open_rate_rising' => true,
                'risk_score' => 0.00,
                'risk_level' => 'low',
                'recommendation_note' => 'Autonomous value active: customer consistently opening push summaries',
            ]);
        }

        // Calculate risk score based on decay days
        $riskScore = min(100.0, round($loginDecayDays * 4.5, 2));
        $riskLevel = ($riskScore >= 50.0) ? 'high' : 'medium';
        $note = "Activity decay over {$loginDecayDays} days: Recommend account executive check-in";

        $score = ChurnScore::create([
            'business_id' => $businessId,
            'tenant_identifier' => $tenantIdentifier,
            'login_decay_days' => $loginDecayDays,
            'roi_open_rate_rising' => false,
            'risk_score' => $riskScore,
            'risk_level' => $riskLevel,
            'recommendation_note' => $note,
        ]);

        if ($riskLevel === 'high') {
            Event::dispatch(new ChurnRiskDetected($businessId, $score->id, $tenantIdentifier, $riskScore));
        }

        return $score;
    }
}
