<?php

declare(strict_types=1);

namespace App\Modules\X126\Events;

final class CapabilityDecided
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $decisionId,
        public readonly string $capabilityName,
        public readonly string $decision,
        public readonly ?string $refusalCode = null
    ) {}
}
