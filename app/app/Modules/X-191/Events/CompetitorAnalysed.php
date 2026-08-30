<?php

declare(strict_types=1);

namespace App\Modules\X191\Events;

final class CompetitorAnalysed
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $domain,
        public readonly int $targetsFound
    ) {}
}
