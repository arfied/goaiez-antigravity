<?php

declare(strict_types=1);

namespace App\Modules\X202\Events;

final class ApprovalDecided
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $approvalItemId,
        public readonly string $decision // approved, rejected
    ) {}
}
