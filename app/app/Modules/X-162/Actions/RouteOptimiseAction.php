<?php

declare(strict_types=1);

namespace App\Modules\X162\Actions;

use App\Modules\X162\Events\RouteChanged;
use App\Modules\X162\Models\Route;
use Illuminate\Support\Facades\Event;

final class RouteOptimiseAction
{
    public function handle(int $businessId, int $techId, array $stopOrder, float $totalDistanceKm = 14.5): Route
    {
        $route = Route::updateOrCreate(
            ['business_id' => $businessId, 'tech_id' => $techId],
            ['stop_order' => $stopOrder, 'total_distance_km' => $totalDistanceKm]
        );

        Event::dispatch(new RouteChanged($businessId, $techId, $stopOrder));

        return $route;
    }
}
