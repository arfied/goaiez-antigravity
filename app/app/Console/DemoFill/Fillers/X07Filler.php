<?php

declare(strict_types=1);

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X07\Models\Forecast;

class X07Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-07';
    }

    public function fill(Business $business): int
    {
        if (Forecast::where('business_id', $business->id)->where('period_month', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        Forecast::create(['business_id' => $business->id, 'period_month' => self::MARKER.'October 2026', 'booked_cents' => 100000, 'collected_cents' => 80000, 'churn_risk_pct' => 5, 'is_high_risk' => false, 'alert_created' => false]);
        Forecast::create(['business_id' => $business->id, 'period_month' => self::MARKER.'November 2026', 'booked_cents' => 200000, 'collected_cents' => 150000, 'churn_risk_pct' => 15, 'is_high_risk' => true, 'alert_created' => true]);

        return 2;
    }

    public function purge(Business $business): int
    {
        return Forecast::where('business_id', $business->id)->where('period_month', 'like', self::MARKER.'%')->delete();
    }
}
