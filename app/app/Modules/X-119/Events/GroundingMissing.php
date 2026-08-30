<?php

declare(strict_types=1);

namespace App\Modules\X119\Events;

final class GroundingMissing
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $queryKey,
        public readonly string $reason
    ) {}
}
