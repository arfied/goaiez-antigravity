<?php

declare(strict_types=1);

namespace App\Modules\X156\Events;

final class IngestedNormalised
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $sourceId,
        public readonly string $attestationId,
        public readonly int $count
    ) {}
}
