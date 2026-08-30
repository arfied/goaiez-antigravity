<?php

declare(strict_types=1);

namespace App\Modules\X203\Events;

final class DrRestoreExecuted
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $backupId,
        public readonly string $targetTimestamp
    ) {}
}
