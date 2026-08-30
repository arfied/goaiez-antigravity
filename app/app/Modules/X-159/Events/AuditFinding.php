<?php

declare(strict_types=1);

namespace App\Modules\X159\Events;

final class AuditFinding
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $auditId,
        public readonly string $findingKey,
        public readonly string $method
    ) {}
}
