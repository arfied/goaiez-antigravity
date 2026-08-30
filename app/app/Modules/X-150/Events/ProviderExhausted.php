<?php

declare(strict_types=1);

namespace App\Modules\X150\Events;

final class ProviderExhausted
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $requestId
    ) {}
}
