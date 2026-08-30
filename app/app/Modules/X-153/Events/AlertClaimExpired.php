<?php

declare(strict_types=1);

namespace App\Modules\X153\Events;

final class AlertClaimExpired
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $alertId
    ) {}
}
