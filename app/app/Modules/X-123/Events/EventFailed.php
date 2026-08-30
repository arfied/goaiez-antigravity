<?php

declare(strict_types=1);

namespace App\Modules\X123\Events;

final class EventFailed
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $eventLogId,
        public readonly int $subscriptionId,
        public readonly string $error,
        public readonly int $attempts,
    ) {}
}
