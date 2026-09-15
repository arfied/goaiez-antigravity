<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X170\Models\Commission;
use App\Modules\X170\Models\Scorecard;

class X170Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-170';
    }

    public function fill(Business $business): int
    {
        if (Commission::where('business_id', $business->id)->where('status', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }
        for ($i = 0; $i < 3; $i++) {
            Commission::create(['business_id' => $business->id, 'invoice_id' => 100 + $i, 'staff_id' => $business->owner_user_id, 'amount_cents' => 5000, 'status' => self::MARKER.'pending_cash_collection']);
        }
        Scorecard::create(['business_id' => $business->id, 'staff_id' => $business->owner_user_id, 'period_key' => self::MARKER.'Q3', 'average_rating' => 4.5]);

        return 4;
    }

    public function purge(Business $business): int
    {
        $count = Commission::where('business_id', $business->id)->where('status', 'like', self::MARKER.'%')->delete();
        $count += Scorecard::where('business_id', $business->id)->where('period_key', 'like', self::MARKER.'%')->delete();

        return $count;
    }
}
