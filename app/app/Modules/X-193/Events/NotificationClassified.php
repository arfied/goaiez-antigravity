<?php

declare(strict_types=1);

namespace App\Modules\X193\Events;

final class NotificationClassified
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $callerType,
        public readonly string $classification,
        public readonly string $deliveryDecision
    ) {}
}
