<?php

declare(strict_types=1);

namespace App\Modules\X166\Events;

final class MarginBelowThreshold
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $jobId,
        public readonly float $grossMarginPct,
        public readonly float $thresholdPct = 20.0
    ) {}
}
