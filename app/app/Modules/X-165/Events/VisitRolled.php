<?php

declare(strict_types=1);

namespace App\Modules\X165\Events;

final class VisitRolled
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $visitId,
        public readonly int $membershipId
    ) {}
}
