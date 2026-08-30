<?php

declare(strict_types=1);

namespace App\Modules\X110\Events;

final class VisitStarted
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $visitId,
        public readonly string $visitorId
    ) {}
}
