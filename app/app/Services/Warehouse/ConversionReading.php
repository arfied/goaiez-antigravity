<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Enums\VitalSampleState;

/**
 * One window's conversion rate, or the reason there is not one.
 *
 * ⚠️ **BASIS POINTS RATHER THAN A PERCENTAGE OR A FLOAT.** Every comparison in
 * the speed layer is integer arithmetic — `SiteVitals` says why at length — and
 * a rate of 1.5% is 150 here with nothing to round.
 */
final readonly class ConversionReading
{
    public function __construct(
        public VitalSampleState $state,
        public int $sessions,
        public int $conversions,
        public int $ratePerTenThousand = 0,
    ) {}

    public function isMeasured(): bool
    {
        return $this->state === VitalSampleState::Measured;
    }

    /**
     * @return array{state: string, sessions: int, conversions: int, rate_bp: int}
     */
    public function toArray(): array
    {
        return [
            'state' => $this->state->value,
            'sessions' => $this->sessions,
            'conversions' => $this->conversions,
            'rate_bp' => $this->ratePerTenThousand,
        ];
    }
}
