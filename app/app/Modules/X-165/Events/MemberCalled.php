<?php

declare(strict_types=1);

namespace App\Modules\X165\Events;

final class MemberCalled
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $membershipId,
        public readonly int $personId
    ) {}
}
