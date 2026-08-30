<?php

declare(strict_types=1);

namespace App\Modules\X153\Events;

final class AlertOverridden
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $alertId,
        public readonly int $newUserId
    ) {}
}
