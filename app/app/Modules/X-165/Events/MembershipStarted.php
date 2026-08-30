<?php

declare(strict_types=1);

namespace App\Modules\X165\Events;

final class MembershipStarted
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $membershipId,
        public readonly int $personId,
        public readonly int $planId
    ) {}
}
