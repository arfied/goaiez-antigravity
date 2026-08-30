<?php

declare(strict_types=1);

namespace App\Modules\X190\Events;

final class ApprovalRequested
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $slotId,
        public readonly int $partnerId
    ) {}
}
