<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X16\Models\GeoGrid;
use App\Modules\X16\Models\ServicePolygon;

class X16Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-16';
    }

    public function fill(Business $business): int
    {
        if (GeoGrid::where('business_id', $business->id)->where('grid_name', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $points = array_fill(0, 25, ['rank' => null]);
        $ranks = [1, 2, 1, 3, 2, 1, 4, 2, 3, 1, 2, 5];
        foreach ($ranks as $i => $rank) {
            $points[$i]['rank'] = $rank;
        }

        GeoGrid::create([
            'business_id' => $business->id,
            'grid_name' => self::MARKER.'Downtown grid',
            'center_lat' => 30.2672,
            'center_lng' => -97.7431,
            'radius_km' => 10,
            'grid_points' => $points,
        ]);

        ServicePolygon::create([
            'business_id' => $business->id,
            'polygon_name' => self::MARKER.'North side',
            'coordinates' => [[30.30, -97.80], [30.40, -97.80], [30.40, -97.70], [30.30, -97.70]],
            'is_active' => true,
        ]);

        ServicePolygon::create([
            'business_id' => $business->id,
            'polygon_name' => self::MARKER.'South side',
            'coordinates' => [[30.10, -97.80], [30.20, -97.80], [30.20, -97.70], [30.10, -97.70]],
            'is_active' => false,
        ]);

        return 3;
    }

    public function purge(Business $business): int
    {
        $gridsCount = GeoGrid::where('business_id', $business->id)
            ->where('grid_name', 'like', self::MARKER.'%')
            ->delete();

        $polygonsCount = ServicePolygon::where('business_id', $business->id)
            ->where('polygon_name', 'like', self::MARKER.'%')
            ->delete();

        return $gridsCount + $polygonsCount;
    }
}
