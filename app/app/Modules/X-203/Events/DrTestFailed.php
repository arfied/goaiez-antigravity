<?php

declare(strict_types=1);

namespace App\Modules\X203\Events;

final class DrTestFailed
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $testId,
        public readonly string $backupId,
        public readonly string $failureReason
    ) {}
}
