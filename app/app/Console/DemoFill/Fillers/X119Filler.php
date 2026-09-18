<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use Illuminate\Support\Facades\DB;

class X119Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-119';
    }

    public function fill(Business $business): int
    {
        if (DB::table('facts')->where('business_id', $business->id)->where('key', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        DB::table('facts')->insert([
            [
                'business_id' => $business->id,
                'key' => self::MARKER.'service.water_heater.price_cents',
                'value' => '49900',
                'is_valid' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'business_id' => $business->id,
                'key' => self::MARKER.'hours.saturday',
                'value' => '8am to 2pm',
                'is_valid' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'business_id' => $business->id,
                'key' => self::MARKER.'service.emergency_callout.price_cents',
                'value' => '18900',
                'is_valid' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        return 3;
    }

    public function purge(Business $business): int
    {
        return DB::table('facts')
            ->where('business_id', $business->id)
            ->where('key', 'like', self::MARKER.'%')
            ->delete();
    }
}
