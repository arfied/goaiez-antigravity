<?php

declare(strict_types=1);

namespace App\Modules\X162\Events;

final class TechEnRoute
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $jobId,
        public readonly int $techId,
        public readonly ?int $etaMinutes
    ) {}
}
