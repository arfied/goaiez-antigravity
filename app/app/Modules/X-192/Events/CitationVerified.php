<?php

declare(strict_types=1);

namespace App\Modules\X192\Events;

final class CitationVerified
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $citationId,
        public readonly bool $isConsistent
    ) {}
}
