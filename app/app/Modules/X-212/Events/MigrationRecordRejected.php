<?php

declare(strict_types=1);

namespace App\Modules\X212\Events;

final class MigrationRecordRejected
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $migrationRunId,
        public readonly int $recordIndex,
        public readonly string $reason
    ) {}
}
