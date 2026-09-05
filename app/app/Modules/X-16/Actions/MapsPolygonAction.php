<?php

declare(strict_types=1);

namespace App\Modules\X16\Actions;

use App\Modules\X16\Events\ServiceOutOfScope;
use App\Modules\X16\Models\ServicePolygon;
use Illuminate\Support\Facades\Event;

final class MapsPolygonAction
{
    public function checkCoverage(int $businessId, float $lat, float $lng): array
    {
        $polygons = ServicePolygon::where('business_id', $businessId)->where('is_active', true)->get();

        $inCoverage = ! $polygons->isEmpty();

        if (! $inCoverage) {
            Event::dispatch(new ServiceOutOfScope($businessId, $lat, $lng, 'Location outside active service polygons'));

            return [
                'in_coverage' => false,
                'status' => 'out_of_scope',
            ];
        }

        return [
            'in_coverage' => true,
            'status' => 'covered',
        ];
    }

    /**
     * [G17-22]
     */
    public function define(int $businessId, string $name, array $points): ServicePolygon
    {
        if (count($points) < 3) {
            throw new \DomainException('Polygon requires at least 3 points.');
        }

        $lats = array_column($points, 0);
        $lngs = array_column($points, 1);

        $latDiff = max($lats) - min($lats);
        $lngDiff = max($lngs) - min($lngs);

        if ($latDiff < 0.002 && $lngDiff < 0.002) {
            throw new \DomainException('a fence around one building is geo-fenced ad targeting (§44 · P-128)');
        }

        return ServicePolygon::create([
            'business_id' => $businessId,
            'polygon_name' => $name,
            'coordinates' => $points,
            'is_active' => true,
        ]);
    }

    public function setActive(int $businessId, int $polygonId, bool $active): ServicePolygon
    {
        $polygon = ServicePolygon::where('business_id', $businessId)->findOrFail($polygonId);
        $polygon->update(['is_active' => $active]);

        return $polygon;
    }
}
