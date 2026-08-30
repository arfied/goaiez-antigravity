<?php

declare(strict_types=1);

namespace App\Modules\CAi\Events;

final class AiCalled
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $aiCallId,
        public readonly string $modelRequested,
        public readonly string $modelServed,
        public readonly int $costCents
    ) {}
}
