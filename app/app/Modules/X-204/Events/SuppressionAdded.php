<?php

declare(strict_types=1);

namespace App\Modules\X204\Events;

final class SuppressionAdded
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $recipientPhone,
        public readonly string $reason
    ) {}
}
