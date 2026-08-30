<?php

declare(strict_types=1);

namespace App\Modules\X130\Events;

final class SeasonTurned
{
    public function __construct(
        public readonly int $regionId,
        public readonly string $seasonName,
        public readonly float $shiftFactor
    ) {}
}
