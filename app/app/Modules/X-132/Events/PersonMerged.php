<?php

declare(strict_types=1);

namespace App\Modules\X132\Events;

final class PersonMerged
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $canonicalPersonId,
        public readonly int $mergedPersonId
    ) {}
}
