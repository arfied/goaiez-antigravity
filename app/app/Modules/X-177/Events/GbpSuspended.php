<?php

declare(strict_types=1);

namespace App\Modules\X177\Events;

final class GbpSuspended
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $connectionId
    ) {}
}
