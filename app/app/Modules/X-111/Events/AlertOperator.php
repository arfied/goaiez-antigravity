<?php

declare(strict_types=1);

namespace App\Modules\X111\Events;

final class AlertOperator
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $alertId,
        public readonly string $severity,
        public readonly string $actionVerbMessage
    ) {}
}
