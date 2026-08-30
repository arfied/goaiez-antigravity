<?php

declare(strict_types=1);

namespace App\Modules\X166\Events;

final class JobCosted
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $jobId,
        public readonly string $priceBookVersion,
        public readonly int $grossMarginCents,
        public readonly float $grossMarginPct
    ) {}
}
