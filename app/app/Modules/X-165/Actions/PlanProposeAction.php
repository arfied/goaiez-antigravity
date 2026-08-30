<?php

declare(strict_types=1);

namespace App\Modules\X165\Actions;

use App\Modules\X165\Models\MembershipPlan;

final class PlanProposeAction
{
    public function handle(
        int $businessId,
        string $name,
        int $priceCents = 19900,
        int $intervalMonths = 12,
        int $reminderDays = 7
    ): MembershipPlan {
        return MembershipPlan::create([
            'business_id' => $businessId,
            'name' => $name,
            'price_cents' => $priceCents,
            'billing_interval_months' => $intervalMonths,
            'renewal_reminder_days' => $reminderDays,
        ]);
    }
}
