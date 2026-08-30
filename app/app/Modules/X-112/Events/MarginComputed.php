<?php

declare(strict_types=1);

namespace App\Modules\X112\Events;

final class MarginComputed
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $agencyId,
        public readonly string $serviceType,
        public readonly int $marginCents
    ) {}
}
