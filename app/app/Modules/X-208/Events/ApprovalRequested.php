<?php

declare(strict_types=1);

namespace App\Modules\X208\Events;

final class ApprovalRequested
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $mailPieceId,
        public readonly int $costCents
    ) {}
}
