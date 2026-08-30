<?php

declare(strict_types=1);

namespace App\Modules\X167\Events;

final class InventoryConsumed
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $stockItemId,
        public readonly float $quantityConsumed,
        public readonly float $remainingQuantity
    ) {}
}
