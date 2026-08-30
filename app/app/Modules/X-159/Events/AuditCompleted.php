<?php

declare(strict_types=1);

namespace App\Modules\X159\Events;

final class AuditCompleted
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $auditId,
        public readonly float $overallScore
    ) {}
}
