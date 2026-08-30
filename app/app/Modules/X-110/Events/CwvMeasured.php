<?php

declare(strict_types=1);

namespace App\Modules\X110\Events;

final class CwvMeasured
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $lcpMs,
        public readonly int $fidMs,
        public readonly float $clsScore
    ) {}
}
