<?php

declare(strict_types=1);

namespace App\Modules\X112\Events;

final class ImpersonationStarted
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $agencyId,
        public readonly int $userId,
        public readonly int $targetClientBusinessId
    ) {}
}
