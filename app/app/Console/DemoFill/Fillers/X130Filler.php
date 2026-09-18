<?php

declare(strict_types=1);

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X130\Models\DemandRegion;
use App\Modules\X130\Models\DemandSeries;

class X130Filler implements DemoFiller
{
    public const MARKER = 'demo·';

    public function module(): string
    {
        return 'X-130';
    }

    public function fill(Business $business): int
    {
        if (DemandRegion::where('region_code', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $region1 = DemandRegion::create([
            'region_code' => self::MARKER.'MEM-PLUMB',
            'region_name' => self::MARKER.'Memphis metro',
            'trade_type' => 'plumbing',
        ]);

        DemandSeries::create([
            'region_id' => $region1->id,
            'period_date' => now()->toDateString(),
            'demand_index' => 68.25,
            'source_count' => 7,
            'is_published' => true,
        ]);

        $region2 = DemandRegion::create([
            'region_code' => self::MARKER.'MEM-HVAC',
            'region_name' => self::MARKER.'Memphis metro',
            'trade_type' => 'hvac',
        ]);

        DemandSeries::create([
            'region_id' => $region2->id,
            'period_date' => now()->toDateString(),
            'demand_index' => 81.00,
            'source_count' => 11,
            'is_published' => true,
        ]);

        return 4;
    }

    public function purge(Business $business): int
    {
        $regions = DemandRegion::where('region_code', 'like', self::MARKER.'%')->get();
        $regionIds = $regions->pluck('id');

        $seriesCount = DemandSeries::whereIn('region_id', $regionIds)->delete();
        $regionCount = DemandRegion::where('region_code', 'like', self::MARKER.'%')->delete();

        return $seriesCount + $regionCount;
    }
}
