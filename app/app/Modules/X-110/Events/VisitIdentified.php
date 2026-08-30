<?php

declare(strict_types=1);

namespace App\Modules\X110\Events;

final class VisitIdentified
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $visitorId,
        public readonly int $personId
    ) {}
}
