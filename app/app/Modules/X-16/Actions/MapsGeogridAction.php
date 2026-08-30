<?php

declare(strict_types=1);

namespace App\Modules\X16\Actions;

use App\Modules\X16\Models\GeoGrid;

final class MapsGeogridAction
{
    public function generateGrid(int $businessId, string $gridName, float $centerLat, float $centerLng, int $radiusKm = 10): GeoGrid
    {
        $points = [];
        for ($i = -2; $i <= 2; $i++) {
            for ($j = -2; $j <= 2; $j++) {
                $points[] = [
                    'lat' => round($centerLat + ($i * 0.02), 6),
                    'lng' => round($centerLng + ($j * 0.02), 6),
                    'rank' => null,
                ];
            }
        }

        return GeoGrid::updateOrCreate(
            ['business_id' => $businessId, 'grid_name' => $gridName],
            [
                'center_lat' => $centerLat,
                'center_lng' => $centerLng,
                'radius_km' => $radiusKm,
                'grid_points' => $points,
            ]
        );
    }
}
