<?php

declare(strict_types=1);

namespace App\Modules\X202\Events;

final class ApprovalExpired
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $approvalItemId
    ) {}
}
