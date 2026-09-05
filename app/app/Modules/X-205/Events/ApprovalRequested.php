<?php

declare(strict_types=1);

namespace App\Modules\X205\Events;

final class ApprovalRequested
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $requestType,
        public readonly int $referenceId,
        public readonly int $amountCents,
        public readonly ?string $status = null
    ) {}
}
