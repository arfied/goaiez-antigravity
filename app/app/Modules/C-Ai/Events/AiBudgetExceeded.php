<?php

declare(strict_types=1);

namespace App\Modules\CAi\Events;

final class AiBudgetExceeded
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $attemptedCostCents,
        public readonly int $remainingBudgetCents
    ) {}
}
