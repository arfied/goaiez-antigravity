<?php

declare(strict_types=1);

namespace App\Modules\X217\Events;

final class AffiliateDeclined
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $prospectId,
        public readonly string $reason
    ) {}
}
