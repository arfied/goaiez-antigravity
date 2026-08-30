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
}
