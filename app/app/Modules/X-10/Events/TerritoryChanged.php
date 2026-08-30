<?php

declare(strict_types=1);

namespace App\Modules\X10\Events;

final class TerritoryChanged
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $territoryId
    ) {}
}
