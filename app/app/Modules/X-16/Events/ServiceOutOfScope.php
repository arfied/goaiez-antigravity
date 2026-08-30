<?php

declare(strict_types=1);

namespace App\Modules\X16\Events;

final class ServiceOutOfScope
{
    public function __construct(
        public readonly int $businessId,
        public readonly float $lat,
        public readonly float $lng,
        public readonly string $reason
    ) {}
}
