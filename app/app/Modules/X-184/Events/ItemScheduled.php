<?php

declare(strict_types=1);

namespace App\Modules\X184\Events;

final class ItemScheduled
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $itemId,
        public readonly string $scheduledDate
    ) {}
}
