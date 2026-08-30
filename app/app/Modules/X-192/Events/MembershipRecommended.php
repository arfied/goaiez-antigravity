<?php

declare(strict_types=1);

namespace App\Modules\X192\Events;

final class MembershipRecommended
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $membershipId,
        public readonly int $rankScore
    ) {}
}
