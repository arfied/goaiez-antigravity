<?php

declare(strict_types=1);

namespace App\Modules\X134\Events;

final class EnrichmentRequested
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $domain,
        public readonly int $runId
    ) {}
}
