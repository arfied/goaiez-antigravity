<?php

declare(strict_types=1);

namespace App\Modules\X177\Events;

final class GbpSuspensionRisk
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $connectionId,
        public readonly string $riskReason
    ) {}
}
