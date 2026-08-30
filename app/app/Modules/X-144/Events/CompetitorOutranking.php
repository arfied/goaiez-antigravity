<?php

declare(strict_types=1);

namespace App\Modules\X144\Events;

final class CompetitorOutranking
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $queryId,
        public readonly string $competitorName
    ) {}
}
