<?php

declare(strict_types=1);

namespace App\Modules\X130\Events;

final class DemandShifted
{
    public function __construct(
        public readonly int $regionId,
        public readonly string $periodDate,
        public readonly float $demandScore
    ) {}
}
