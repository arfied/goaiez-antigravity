<?php

declare(strict_types=1);

namespace App\Modules\X141\Events;

final class CounterfactualComputed
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $counterfactualId,
        public readonly string $scenarioKey
    ) {}
}
