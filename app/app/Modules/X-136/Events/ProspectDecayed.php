<?php

declare(strict_types=1);

namespace App\Modules\X136\Events;

final class ProspectDecayed
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $prospectIdentifier,
        public readonly int $daysSinceLastSignal
    ) {}
}
