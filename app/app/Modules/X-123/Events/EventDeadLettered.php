<?php

declare(strict_types=1);

namespace App\Modules\X123\Events;

final class EventDeadLettered
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $deadLetterId,
        public readonly int $eventLogId,
        public readonly string $errorMessage,
    ) {}
}
