<?php

declare(strict_types=1);

namespace App\Modules\X173\Events;

final class AccountingSynced
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $syncRunId,
        public readonly int $recordsSynced,
        public readonly int $conflictsCount
    ) {}
}
