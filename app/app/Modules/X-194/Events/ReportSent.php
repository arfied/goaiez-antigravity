<?php

declare(strict_types=1);

namespace App\Modules\X194\Events;

final class ReportSent
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $scheduleId,
        public readonly int $activityCount
    ) {}
}
