<?php

declare(strict_types=1);

namespace App\Modules\X130\Actions;

use App\Modules\X130\Events\DemandShifted;
use App\Modules\X130\Events\SeasonTurned;
use App\Modules\X130\Models\DemandSeries;
use Illuminate\Support\Facades\Event;

final class DemandPublishAction
{
    private const MIN_SOURCE_COUNT = 5;

    /**
     * Publishes a demand series cell.
     * A cell under the minimum source count is NEVER published (TEST ANCHOR).
     */
    public function publishCell(
        int $regionId,
        string $periodDate,
        float $demandScore,
        int $sourceCount,
        bool $isSeasonalTurn = false
    ): DemandSeries {
        // TEST ANCHOR: A cell under minimum source count is NEVER published
        $canPublish = ($sourceCount >= self::MIN_SOURCE_COUNT);

        $series = DemandSeries::updateOrCreate(
            ['region_id' => $regionId, 'period_date' => $periodDate],
            [
                'demand_score' => $demandScore,
                'source_count' => $sourceCount,
                'is_published' => $canPublish,
            ]
        );

        if ($canPublish) {
            Event::dispatch(new DemandShifted($regionId, $periodDate, $demandScore));

            if ($isSeasonalTurn) {
                Event::dispatch(new SeasonTurned($regionId, 'Summer Surge', 1.35));
            }
        }

        return $series;
    }
}
