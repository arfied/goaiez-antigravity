<?php

declare(strict_types=1);

namespace App\Modules\X210\Actions;

final class PromotionProposeTargetsAction
{
    public function proposeTargets(int $businessId, string $goal = 'retention'): array
    {
        return [
            ['scope_type' => 'customer_segment', 'scope_value' => 'at_risk_90_days'],
            ['scope_type' => 'service_category', 'scope_value' => 'preventive_maintenance'],
        ];
    }
}
