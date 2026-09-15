<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X164\Models\Estimate;

class X164Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-164';
    }

    public function fill(Business $business): int
    {
        if (Estimate::where('business_id', $business->id)->where('estimate_number', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }
        for ($i = 0; $i < 3; $i++) {
            Estimate::create(['business_id' => $business->id, 'estimate_number' => self::MARKER."EST$i", 'total_cents' => 50000, 'status' => 'sent', 'created_at' => now()->subDays(5)]);
        }

        return 3;
    }

    public function purge(Business $business): int
    {
        return Estimate::where('business_id', $business->id)->where('estimate_number', 'like', self::MARKER.'%')->delete();
    }
}
