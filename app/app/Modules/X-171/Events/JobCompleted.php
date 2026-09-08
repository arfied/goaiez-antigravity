<?php

declare(strict_types=1);

namespace App\Modules\X171\Events;

use Carbon\CarbonInterface;

final class JobCompleted
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $jobId,
        public readonly int $techId,
        public readonly ?int $personId = null,
        public readonly ?CarbonInterface $occurredAt = null
    ) {}
}
