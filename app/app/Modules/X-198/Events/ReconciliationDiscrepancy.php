<?php

declare(strict_types=1);

namespace App\Modules\X198\Events;

final class ReconciliationDiscrepancy
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $runId,
        public readonly int $discrepancyCents,
        public readonly string $reason
    ) {}
}
