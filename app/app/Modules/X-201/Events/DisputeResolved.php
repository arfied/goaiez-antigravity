<?php

declare(strict_types=1);

namespace App\Modules\X201\Events;

final class DisputeResolved
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $disputeId
    ) {}
}
