<?php

declare(strict_types=1);

namespace App\Modules\X07\Events;

final class ForecastUpdated
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $forecastId,
        public readonly string $periodMonth
    ) {}
}
