<?php

declare(strict_types=1);

namespace App\Modules\X103\Domain;

use App\Enums\VitalSampleState;

final readonly class PageVariantReading
{
    public function __construct(
        public string $arm,
        public VitalSampleState $state,
        public int $served,
        public int $requests,
        public int $ratePerTenThousand = 0,
    ) {}

    public function isMeasured(): bool
    {
        return $this->state === VitalSampleState::Measured;
    }

    /**
     * @return array{arm: string, state: string, served: int, requests: int, rate_bp: int}
     */
    public function toArray(): array
    {
        return [
            'arm' => $this->arm,
            'state' => $this->state->value,
            'served' => $this->served,
            'requests' => $this->requests,
            'rate_bp' => $this->ratePerTenThousand,
        ];
    }
}
