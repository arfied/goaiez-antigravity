<?php

declare(strict_types=1);

namespace App\Modules\X135\Events;

final class ResearchCompleted
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $prospectId,
        public readonly int $runId
    ) {}
}
