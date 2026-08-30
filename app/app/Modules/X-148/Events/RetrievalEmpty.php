<?php

declare(strict_types=1);

namespace App\Modules\X148\Events;

final class RetrievalEmpty
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $queryText
    ) {}
}
