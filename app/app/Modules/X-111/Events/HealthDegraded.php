<?php

declare(strict_types=1);

namespace App\Modules\X111\Events;

final class HealthDegraded
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $serviceName,
        public readonly string $reason
    ) {}
}
