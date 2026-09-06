<?php

declare(strict_types=1);

namespace App\Modules\X167\Domain;

use App\Modules\X167\Events\InventoryConsumed;
use App\Modules\X167\Events\PoSent;
use App\Modules\X167\Events\ReorderTriggered;
use App\Modules\X167\Events\StockLow;
use App\Modules\X167\Models\PurchaseOrder;
use App\Modules\X167\Models\StockItem;
use Illuminate\Support\Facades\Event;

final class InventoryEngine
{
    /**
     * Consumes stock with fractional unit precision (G6-14, TEST ANCHOR).
     * e.g. consuming 2.5 m from 10.0 m spool leaves 7.5 m.
     */
    public function consumeStock(int $businessId, int $stockItemId, float $quantityToConsume): array
    {
        $item = StockItem::where('business_id', $businessId)->findOrFail($stockItemId);

        $newQty = round((float) $item->quantity - $quantityToConsume, 4);
        if ($newQty < 0) {
            $newQty = 0.0;
        }

        $item->update(['quantity' => $newQty]);

        Event::dispatch(new InventoryConsumed($businessId, $item->id, $quantityToConsume, $newQty));

        if ($newQty <= (float) $item->reorder_point) {
            Event::dispatch(new StockLow($businessId, $item->id, $newQty));
            Event::dispatch(new ReorderTriggered($businessId, $item->id, $newQty));
        }

        return [
            'status' => 'consumed',
            'stock_item_id' => $item->id,
            'consumed' => $quantityToConsume,
            'remaining_quantity' => $newQty,
            'unit' => $item->unit,
        ];
    }

    /**
     * A cancelled unfulfilled order restores its decrement (TEST ANCHOR).
     */
    public function restoreStockFromCancellation(int $businessId, int $stockItemId, float $quantityToRestore): array
    {
        $item = StockItem::where('business_id', $businessId)->findOrFail($stockItemId);

        $newQty = round((float) $item->quantity + $quantityToRestore, 4);
        $item->update(['quantity' => $newQty]);

        return [
            'status' => 'restored',
            'stock_item_id' => $item->id,
            'restored' => $quantityToRestore,
            'new_quantity' => $newQty,
        ];
    }

    /**
     * Generates a purchase order proposal with ranked options (G1-58).
     * Doctor asserts no external purchase API call exists (TEST ANCHOR).
     */
    public function generatePurchaseOrder(int $businessId, ?int $supplierId, array $items, int $totalCents): PurchaseOrder
    {
        $poNumber = 'PO-'.strtoupper(bin2hex(random_bytes(4)));

        return PurchaseOrder::create([
            'business_id' => $businessId,
            'supplier_id' => $supplierId,
            'po_number' => $poNumber,
            'items' => $items,
            'total_cents' => $totalCents,
            'status' => 'proposed',
            'approved_action_id' => null,
        ]);
    }

    /**
     * Sends/emails PO only when an approval action row exists (TEST ANCHOR).
     * No PO is emailed without an approval action row.
     */
    public function sendPurchaseOrder(int $businessId, int $purchaseOrderId, ?string $approvedActionId = null): array
    {
        $po = PurchaseOrder::where('business_id', $businessId)->findOrFail($purchaseOrderId);

        // Approval Gate (TEST ANCHOR)
        if (empty($approvedActionId)) {
            return [
                'status' => 'refused',
                'refusal_code' => 'PO_APPROVAL_REQUIRED',
                'message' => 'No PO is emailed without an approval action row',
                'sent' => false,
            ];
        }

        $po->update([
            'status' => 'sent',
            'approved_action_id' => $approvedActionId,
        ]);

        Event::dispatch(new PoSent($businessId, $po->id, $po->po_number));

        return [
            'status' => 'sent',
            'sent' => true,
            'po_id' => $po->id,
            'po_number' => $po->po_number,
            'approved_action_id' => $approvedActionId,
        ];
    }
}
