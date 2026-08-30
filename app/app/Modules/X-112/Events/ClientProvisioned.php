<?php

declare(strict_types=1);

namespace App\Modules\X112\Events;

final class ClientProvisioned
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $agencyId,
        public readonly int $clientBusinessId,
        public readonly string $clientName
    ) {}
}
