<?php

declare(strict_types=1);

namespace App\Modules\X103\Events;

final class ApprovalRequested
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $itemType,
        public readonly string $subject,
        public readonly array $payload
    ) {}
}
