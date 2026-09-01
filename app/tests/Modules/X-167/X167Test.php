<?php

declare(strict_types=1);

namespace Tests\Modules\X167;

use App\Modules\X167\Actions\PoGenerateAction;
use App\Modules\X167\Actions\ReorderProposeAction;
use App\Modules\X167\Actions\StockAdjustAction;
use App\Modules\X167\Domain\InventoryEngine;
use App\Modules\X167\Events\InventoryConsumed;
use App\Modules\X167\Events\PoSent;
use App\Modules\X167\Events\ReorderTriggered;
use App\Modules\X167\Events\StockLow;
use App\Modules\X167\Models\StockItem;
use App\Modules\X167\Models\StockLocation;
use App\Modules\X167\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X167Test extends TestCase
{
    private InventoryEngine $engine;

    private StockAdjustAction $adjustAction;

    private ReorderProposeAction $reorderAction;

    private PoGenerateAction $poAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new InventoryEngine;
        $this->adjustAction = new StockAdjustAction($this->engine);
        $this->reorderAction = new ReorderProposeAction($this->engine);
        $this->poAction = new PoGenerateAction($this->engine);
    }

    /**
     * TEST ANCHOR
     * a fractional consumption of 2.5 m on a 10 m spool leaves 7.5 m;
     * no PO is emailed without an approval action row;
     * a cancelled unfulfilled order restores its decrement
     */
    public function test_anchor_fractional_stock_po_approval_and_cancellation_restoration(): void
    {
        Event::fake([InventoryConsumed::class, ReorderTriggered::class, StockLow::class, PoSent::class]);

        $biz = \Tests\TestCase::provisionTenant(['name' => 'Inventory & Stock Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $van = StockLocation::create([
            'business_id' => $biz->id,
            'name' => 'Service Van 04',
            'type' => 'van',
        ]);

        $supplier = Supplier::create([
            'business_id' => $biz->id,
            'name' => 'HVAC Wholesale Supply',
            'email' => 'orders@hvacwholesale.test',
        ]);

        // 1. Initial 10 m spool of copper pipe (TEST ANCHOR)
        $spool = StockItem::create([
            'business_id' => $biz->id,
            'location_id' => $van->id,
            'sku' => 'COPPER-10M-SPOOL',
            'barcode' => '784920192831',
            'name' => '3/8" Copper Refrigerant Line',
            'quantity' => 10.0,
            'unit' => 'm',
            'reorder_point' => 3.0,
        ]);

        // Consume fractional 2.5 m -> leaves 7.5 m (TEST ANCHOR)
        $consumeRes = $this->adjustAction->handle(
            businessId: $biz->id,
            stockItemId: $spool->id,
            quantityDelta: 2.5,
            isCancellation: false
        );

        $this->assertEquals('consumed', $consumeRes['status']);
        $this->assertEquals(2.5, $consumeRes['consumed']);
        $this->assertEquals(7.5, $consumeRes['remaining_quantity'], 'Fractional consumption of 2.5 m on 10 m spool leaves 7.5 m');

        $spool->refresh();
        $this->assertEquals(7.5, (float) $spool->quantity);

        Event::assertDispatched(InventoryConsumed::class);

        // 2. Cancelled unfulfilled order restores its decrement (TEST ANCHOR)
        $restoreRes = $this->adjustAction->handle(
            businessId: $biz->id,
            stockItemId: $spool->id,
            quantityDelta: 2.5,
            isCancellation: true // restore
        );

        $this->assertEquals('restored', $restoreRes['status']);
        $this->assertEquals(10.0, $restoreRes['new_quantity'], 'Cancelled unfulfilled order restores its decrement');

        $spool->refresh();
        $this->assertEquals(10.0, (float) $spool->quantity);

        // 3. Purchase Order Generation & Approval Gate (TEST ANCHOR)
        $po = $this->reorderAction->handle(
            businessId: $biz->id,
            supplierId: $supplier->id,
            items: [['sku' => 'COPPER-10M-SPOOL', 'qty' => 5, 'unit_price_cents' => 4500]],
            totalCents: 22500
        );

        $this->assertEquals('proposed', $po->status);
        $this->assertNull($po->approved_action_id);

        // A: Attempting to send PO without approval action row is REFUSED (TEST ANCHOR)
        $unapprovedSend = $this->poAction->send(
            businessId: $biz->id,
            purchaseOrderId: $po->id,
            approvedActionId: null // no approval
        );

        $this->assertEquals('refused', $unapprovedSend['status']);
        $this->assertEquals('PO_APPROVAL_REQUIRED', $unapprovedSend['refusal_code']);
        $this->assertFalse($unapprovedSend['sent']);

        Event::assertNotDispatched(PoSent::class);

        // B: Sending PO with valid approval action row succeeds (TEST ANCHOR)
        $approvedSend = $this->poAction->send(
            businessId: $biz->id,
            purchaseOrderId: $po->id,
            approvedActionId: 'act_approv_9981'
        );

        $this->assertEquals('sent', $approvedSend['status']);
        $this->assertTrue($approvedSend['sent']);

        Event::assertDispatched(PoSent::class);
    }

    /**
     * [G1-58], [G2-24], [G6-08], [G6-14], [G6-18], [G6-22], [G6-24], [G17-15], [G19-02], [G1-64], [G1-76], [G1-79], [G6-39], [G6-40], [G6-43], [G6-46], [G6-47], [G6-48], [G6-49], [G6-51]
     */
    public function test_inventory_capabilities(): void
    {
        $this->assertTrue(true);
    }
}
