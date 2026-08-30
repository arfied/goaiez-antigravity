<?php

declare(strict_types=1);

namespace App\Modules\X123\Events;

final class EventPublished
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $eventLogId,
        public readonly string $eventName,
        public readonly array $payload,
    ) {}
}
