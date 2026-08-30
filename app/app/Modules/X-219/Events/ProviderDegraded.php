<?php

declare(strict_types=1);

namespace App\Modules\X219\Events;

final class ProviderDegraded
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $providerName,
        public readonly int $errorRatePct
    ) {}
}
