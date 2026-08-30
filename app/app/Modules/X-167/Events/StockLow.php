<?php

declare(strict_types=1);

namespace App\Modules\X167\Events;

final class StockLow
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $stockItemId,
        public readonly float $quantity
    ) {}
}
