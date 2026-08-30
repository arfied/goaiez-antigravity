<?php

declare(strict_types=1);

namespace App\Modules\X159\Events;

final class ExperientialTested
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $prospectId,
        public readonly string $testType,
        public readonly bool $passed
    ) {}
}
