<?php

declare(strict_types=1);

namespace App\Modules\X197\Events;

final class VoiceRouteSelected
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $routeId,
        public readonly string $routeType,
        public readonly float $costPerMinute
    ) {}
}
