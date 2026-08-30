<?php

declare(strict_types=1);

namespace App\Modules\X105\Events;

final class DemoRequested
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $personId,
        public readonly string $preferredTime
    ) {}
}
