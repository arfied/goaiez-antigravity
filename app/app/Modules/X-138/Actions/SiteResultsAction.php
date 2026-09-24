<?php

declare(strict_types=1);

namespace App\Modules\X138\Actions;

use App\Enums\VitalSampleState;
use App\Modules\X108\Actions\BookingReadAction;
use App\Modules\X137\Actions\CallAttributionReadAction;
use App\Services\Warehouse\SiteConversions;
use Carbon\CarbonImmutable;

/**
 * What the website brought in over a window, from measured stores only:
 * sessions from the deployed page's pixel (warehouse mart, RLS-scoped),
 * tracked-number calls joined to a web session (X-137), booking requests and
 * booked jobs (X-108). Nothing here is an argument somebody must pass — every
 * number is a count of rows that exist. `visits` is null when the pixel has
 * never measured this tenant, and the screen says so instead of printing 0.
 *
 * @return array{days: int, visits: ?int, calls: int, booking_requests: int, booked: int}
 */
final class SiteResultsAction
{
    public function __construct(
        private readonly SiteConversions $conversions,
        private readonly CallAttributionReadAction $calls,
        private readonly BookingReadAction $bookings,
    ) {}

    public function handle(int $businessId, int $days = 30): array
    {
        $since = CarbonImmutable::now()->subDays($days);
        $reading = $this->conversions->rate($since, CarbonImmutable::now());

        return [
            'days' => $days,
            'visits' => $reading->state === VitalSampleState::NoMeasurements ? null : $reading->sessions,
            'calls' => $this->calls->joinedSince($businessId, $since),
            'booking_requests' => $this->bookings->requestsSince($businessId, $since),
            'booked' => $this->bookings->bookedSince($businessId, $since),
        ];
    }
}
