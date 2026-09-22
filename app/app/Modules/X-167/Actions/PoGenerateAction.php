<?php

declare(strict_types=1);

namespace App\Modules\X167\Actions;

use App\Modules\X167\Domain\InventoryEngine;

final class PoGenerateAction
{
    public function __construct(private readonly InventoryEngine $engine = new InventoryEngine) {}

    public function handle(int $businessId, int $purchaseOrderId, ?string $approvedActionId = null): array
    {
        return $this->engine->sendPurchaseOrder($businessId, $purchaseOrderId, $approvedActionId);
    }
}
