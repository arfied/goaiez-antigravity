<?php

declare(strict_types=1);

namespace App\Modules\X10\Events;

final class LeadReassigned
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $leadId,
        public readonly int $newStaffId,
        public readonly string $reason
    ) {}
}
