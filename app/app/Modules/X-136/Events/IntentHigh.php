<?php

declare(strict_types=1);

namespace App\Modules\X136\Events;

final class IntentHigh
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $scoreId,
        public readonly string $prospectIdentifier,
        public readonly float $score
    ) {}
}
