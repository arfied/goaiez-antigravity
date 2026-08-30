<?php

declare(strict_types=1);

namespace App\Modules\X161\Events;

final class DemoStopReceived
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $demoTenantId,
        public readonly string $sourceChannel
    ) {}
}
