<?php

declare(strict_types=1);

namespace App\Modules\X162\Events;

final class JobDispatched
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $jobId,
        public readonly int $techId
    ) {}
}
