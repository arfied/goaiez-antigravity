<?php

declare(strict_types=1);

namespace App\Modules\X167\Actions;

use App\Modules\X167\Domain\InventoryEngine;
use App\Modules\X167\Models\PurchaseOrder;

final class ReorderProposeAction
{
    public function __construct(private readonly InventoryEngine $engine = new InventoryEngine) {}

    public function handle(int $businessId, ?int $supplierId, array $items, int $totalCents): PurchaseOrder
    {
        return $this->engine->generatePurchaseOrder($businessId, $supplierId, $items, $totalCents);
    }
}
