<?php

declare(strict_types=1);

namespace App\Modules\X220\Events;

final class EvalRegressed
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $promptId,
        public readonly int $score,
        public readonly int $threshold
    ) {}
}
