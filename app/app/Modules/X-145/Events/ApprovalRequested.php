<?php

declare(strict_types=1);

namespace App\Modules\X145\Events;

final class ApprovalRequested
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $decisionId,
        public readonly string $action
    ) {}
}
