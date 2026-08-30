<?php

declare(strict_types=1);

namespace App\Modules\X121\Events;

final class NumberNeeded
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $purpose = 'outreach',
    ) {}
}
