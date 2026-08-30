<?php

declare(strict_types=1);

namespace App\Modules\X01\Events;

final class LeadScored
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $personId,
        public readonly int $score,
        public readonly string $grade
    ) {}
}
