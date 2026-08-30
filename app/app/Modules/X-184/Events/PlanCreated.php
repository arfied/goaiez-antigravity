<?php

declare(strict_types=1);

namespace App\Modules\X184\Events;

final class PlanCreated
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $planId,
        public readonly string $weekLabel
    ) {}
}
