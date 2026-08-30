<?php

declare(strict_types=1);

namespace App\Modules\X126\Events;

final class CapabilityRefused
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $decisionId,
        public readonly string $capabilityName,
        public readonly string $refusalCode,
        public readonly string $reason
    ) {}
}
