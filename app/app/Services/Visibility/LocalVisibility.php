<?php

declare(strict_types=1);

namespace App\Services\Visibility;

use App\Models\Location;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;

/**
 * The Normal visibility surface for one location (`28` §5.3 + §5.5).
 *
 * Composes what we can measure today (GSC + Places competitors) with honest
 * statements for what we cannot (GBP Performance Maps impressions, catchment
 * map — pixel unbuilt, GBP Performance unapproved; decision 1081).
 *
 * Never invents a zero for an unavailable source (1084).
 */
final class LocalVisibility
{
    public function __construct(
        private readonly VisibilityReadings $gsc,
        private readonly CompetitorSignals $competitors,
    ) {}

    public function for(Location $location, ?CarbonImmutable $asOf = null): LocalVisibilityReport
    {
        Tenancy::idOrFail();

        $asOf ??= CarbonImmutable::now();
        $currentEnd = $asOf->subDays(2)->startOfDay();
        $currentStart = $currentEnd->subDays(27);
        $earlierEnd = $currentStart->subDay();
        $earlierStart = $earlierEnd->subDays(27);

        $current = $this->gsc->read($location, $currentStart, $currentEnd);
        $earlier = $this->gsc->read($location, $earlierStart, $earlierEnd);
        $movement = VisibilityMovement::between($current, $earlier);

        return new LocalVisibilityReport(
            search: $current,
            searchMovement: $movement,
            earlierSearch: $earlier,
            competitors: $this->competitors->compare($location),
            mapsImpressionsAvailable: false,
            catchmentAvailable: false,
            mapsUnavailableReason: 'gbp_performance_unavailable',
            catchmentUnavailableReason: 'pixel_unbuilt',
        );
    }
}
