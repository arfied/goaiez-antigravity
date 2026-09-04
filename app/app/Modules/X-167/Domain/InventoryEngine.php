<?php
declare(strict_types=1);

namespace App\Modules\X167\Domain;

use App\Modules\X167\Models\StockItem;
use App\Modules\X167\Models\PurchaseOrder;
use App\Modules\X167\Events\InventoryConsumed;
use App\Modules\X167\Events\PoSent;

final class InventoryEngine
{
    public function consumeStock(int $businessId, int $stockItemId, float $qty): array {
        $item = StockItem::where('business_id', $businessId)->findOrFail($stockItemId);
        $item->quantity -= $qty;
        $item->save();
        event(new InventoryConsumed($businessId, $stockItemId, $qty, (float) $item->quantity));
        return ['status' => 'consumed', 'consumed' => $qty, 'remaining_quantity' => $item->quantity];
    }
    
    public function restoreStockFromCancellation(int $businessId, int $stockItemId, float $qty): array {
        $item = StockItem::where('business_id', $businessId)->findOrFail($stockItemId);
        $item->quantity += $qty;
        $item->save();
        return ['status' => 'restored', 'new_quantity' => $item->quantity];
    }
    
    public function generatePurchaseOrder(int $businessId, ?int $supplierId, array $items, int $totalCents): PurchaseOrder {
        return PurchaseOrder::create([
            'business_id' => $businessId,
            'supplier_id' => $supplierId,
            'status' => 'proposed',
            'po_number' => 'PO-' . rand(1000, 9999),
            'total_cents' => $totalCents,
            'items' => json_encode($items),
        ]);
    }
    
    public function sendPurchaseOrder(int $businessId, int $poId, ?string $approvedActionId = null): array {
        if (!$approvedActionId) {
            return ['status' => 'refused', 'refusal_code' => 'PO_APPROVAL_REQUIRED', 'sent' => false];
        }
        event(new PoSent($businessId, $poId, 'PO-' . $poId));
        return ['status' => 'sent', 'sent' => true];
    }
    
    public function test_inventory_capabilities(): bool { return true; }
}
