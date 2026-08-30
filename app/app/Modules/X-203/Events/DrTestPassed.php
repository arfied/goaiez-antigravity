<?php

declare(strict_types=1);

namespace App\Modules\X203\Events;

final class DrTestPassed
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $testId,
        public readonly string $backupId,
        public readonly string $checksum
    ) {}
}
