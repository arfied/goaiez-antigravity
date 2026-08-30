<?php

declare(strict_types=1);

namespace App\Modules\X167\Actions;

use App\Modules\X167\Domain\InventoryEngine;

final class StockAdjustAction
{
    public function __construct(private readonly InventoryEngine $engine = new InventoryEngine) {}

    public function handle(int $businessId, int $stockItemId, float $quantityDelta, bool $isCancellation = false): array
    {
        if ($isCancellation) {
            return $this->engine->restoreStockFromCancellation($businessId, $stockItemId, abs($quantityDelta));
        }

        return $this->engine->consumeStock($businessId, $stockItemId, abs($quantityDelta));
    }
}
