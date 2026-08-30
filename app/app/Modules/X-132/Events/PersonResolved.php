<?php

declare(strict_types=1);

namespace App\Modules\X132\Events;

final class PersonResolved
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $canonicalPersonId,
        public readonly string $matchTier,
        public readonly float $confidenceScore
    ) {}
}
