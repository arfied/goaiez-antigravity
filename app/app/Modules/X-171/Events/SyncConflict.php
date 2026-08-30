<?php

declare(strict_types=1);

namespace App\Modules\X171\Events;

final class SyncConflict
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $queueId,
        public readonly string $deviceId,
        public readonly string $reason
    ) {}
}
