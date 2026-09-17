<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X157\Models\Deployment;
use App\Modules\X157\Models\EdgeZone;

class X157Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-157';
    }

    public function fill(Business $business): int
    {
        if (EdgeZone::where('business_id', $business->id)->where('domain_name', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $zone = EdgeZone::create([
            'business_id' => $business->id,
            'domain_name' => self::MARKER.'example.com',
            'provider' => 'demo',
            'zone_id' => 'z1',
            'has_valid_ssl' => true,
            'status' => 'active',
        ]);

        Deployment::create([
            'business_id' => $business->id,
            'edge_zone_id' => $zone->id,
            'deploy_hash' => self::MARKER.'a1b2c3d4',
            'status' => 'live',
            'speed_index' => 100,
            'speed_budget_ms' => 200,
            'measured_ttfb_ms' => 50,
            'rollback_reason' => null,
            'deployed_at' => now(),
            'page_id' => 1,
        ]);

        return 2;
    }

    public function purge(Business $business): int
    {
        $deployCount = Deployment::where('business_id', $business->id)
            ->where('deploy_hash', 'like', self::MARKER.'%')
            ->delete();

        $zoneCount = EdgeZone::where('business_id', $business->id)
            ->where('domain_name', 'like', self::MARKER.'%')
            ->delete();

        return $deployCount + $zoneCount;
    }
}
