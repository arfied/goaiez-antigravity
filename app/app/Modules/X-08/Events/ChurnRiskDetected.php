<?php

declare(strict_types=1);

namespace App\Modules\X08\Events;

final class ChurnRiskDetected
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $scoreId,
        public readonly string $tenantIdentifier,
        public readonly float $riskScore
    ) {}
}
