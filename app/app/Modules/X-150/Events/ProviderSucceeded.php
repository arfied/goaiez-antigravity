<?php

declare(strict_types=1);

namespace App\Modules\X150\Events;

final class ProviderSucceeded
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $requestId,
        public readonly int $tierLevel,
        public readonly string $providerName
    ) {}
}
