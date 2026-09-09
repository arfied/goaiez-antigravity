<?php

declare(strict_types=1);

namespace App\Modules\X171\Events;

use Carbon\CarbonInterface;

final class TechOnSite
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $jobId,
        public readonly int $techId,
        public readonly CarbonInterface $occurredAt
    ) {}
}
