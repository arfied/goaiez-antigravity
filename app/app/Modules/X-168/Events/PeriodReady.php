<?php

declare(strict_types=1);

namespace App\Modules\X168\Events;

final class PeriodReady
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $timesheetId,
        public readonly string $periodEnd
    ) {}
}
