<?php

declare(strict_types=1);

namespace App\Modules\X131\Events;

final class InterestOverridden
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $personId,
        public readonly string $topic
    ) {}
}
