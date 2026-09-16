<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X10\Models\RoutingRule;
use App\Modules\X10\Models\Territory;

class X10Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-10';
    }

    public function fill(Business $business): int
    {
        if (RoutingRule::where('business_id', $business->id)->where('name', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        RoutingRule::create(['business_id' => $business->id, 'name' => self::MARKER.'Rule 1', 'rule_type' => 'geo', 'priority' => 1, 'is_active' => true]);
        Territory::create(['business_id' => $business->id, 'name' => self::MARKER.'Area 51', 'polygon_geojson' => [], 'zip_codes' => []]);

        return 2;
    }

    public function purge(Business $business): int
    {
        $count = RoutingRule::where('business_id', $business->id)->where('name', 'like', self::MARKER.'%')->delete();
        $count += Territory::where('business_id', $business->id)->where('name', 'like', self::MARKER.'%')->delete();

        return $count;
    }
}
