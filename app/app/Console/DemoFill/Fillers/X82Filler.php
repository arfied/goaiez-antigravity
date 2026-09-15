<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X82\Models\Rate;

class X82Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-82';
    }

    public function fill(Business $business): int
    {
        if (Rate::where('business_id', $business->id)->where('rate_code', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }
        for ($i = 0; $i < 3; $i++) {
            Rate::create(['business_id' => $business->id, 'rate_code' => self::MARKER."RATE_$i", 'amount_cents' => 1000, 'currency' => 'USD', 'current_version' => 1]);
        }

        return 3;
    }

    public function purge(Business $business): int
    {
        return Rate::where('business_id', $business->id)->where('rate_code', 'like', self::MARKER.'%')->delete();
    }
}
