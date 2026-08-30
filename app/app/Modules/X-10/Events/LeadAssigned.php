<?php

declare(strict_types=1);

namespace App\Modules\X10\Events;

final class LeadAssigned
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $leadId,
        public readonly int $staffId,
        public readonly string $reason
    ) {}
}
