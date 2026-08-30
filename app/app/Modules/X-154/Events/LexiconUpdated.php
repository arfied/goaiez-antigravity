<?php

declare(strict_types=1);

namespace App\Modules\X154\Events;

final class LexiconUpdated
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $genericTerm,
        public readonly string $preferredTerm
    ) {}
}
