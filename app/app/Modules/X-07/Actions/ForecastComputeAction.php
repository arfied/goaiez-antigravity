<?php

declare(strict_types=1);

namespace App\Modules\X07\Actions;

use App\Modules\X07\Events\ForecastUpdated;
use App\Modules\X07\Models\Forecast;
use Illuminate\Support\Facades\Event;

final class ForecastComputeAction
{
    /**
     * Computes monthly forecast keeping booked and collected strictly distinct (G1-36, G1-46, G1-47).
     */
    public function compute(int $businessId, string $periodMonth, int $bookedCents, int $collectedCents): Forecast
    {
        $forecast = Forecast::updateOrCreate(
            ['business_id' => $businessId, 'period_month' => $periodMonth],
            [
                'booked_cents' => $bookedCents,
                'collected_cents' => $collectedCents,
            ]
        );

        Event::dispatch(new ForecastUpdated($businessId, $forecast->id, $periodMonth));

        return $forecast;
    }
}
