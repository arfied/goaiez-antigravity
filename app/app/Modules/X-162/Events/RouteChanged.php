<?php

declare(strict_types=1);

namespace App\Modules\X162\Events;

final class RouteChanged
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $techId,
        public readonly array $newStopOrder
    ) {}
}
