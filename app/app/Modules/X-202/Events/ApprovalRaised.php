<?php

declare(strict_types=1);

namespace App\Modules\X202\Events;

final class ApprovalRaised
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $approvalItemId,
        public readonly string $itemType,
        public readonly string $subject
    ) {}
}
