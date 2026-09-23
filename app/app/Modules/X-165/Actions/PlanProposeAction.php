<?php

declare(strict_types=1);

namespace App\Modules\X165\Actions;

use App\Modules\X165\Models\MembershipPlan;
use App\Services\Config\DefaultsRegistry;

final class PlanProposeAction
{
    public const DEFAULT_INTERVAL_MONTHS = 12;

    private function defaultIntervalMonths(): int
    {
        return $this->registry->int('plans.default_interval_months');
    }

    public function __construct(private DefaultsRegistry $registry) {}

    public function handle(
        int $businessId,
        string $name,
        int $priceCents,
        ?int $intervalMonths = null,
        int $reminderDays = 7
    ): MembershipPlan {
        $intervalMonths ??= $this->defaultIntervalMonths();

        return MembershipPlan::create([
            'business_id' => $businessId,
            'name' => $name,
            'price_cents' => $priceCents,
            'billing_interval_months' => $intervalMonths,
            'renewal_reminder_days' => $reminderDays,
        ]);
    }
}
