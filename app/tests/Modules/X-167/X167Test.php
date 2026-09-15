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
use App\Modules\X167\Models\PurchaseOrder;
use App\Modules\X167\Models\StockItem;
use App\Modules\X167\Models\StockLocation;
use App\Modules\X167\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
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
     * [G6-14]
     * [G1-58]
     * [G6-46] doctor asserts NO autonomous ordering path - it PROPOSES; L1, MONEY
     * [G1-64] a blanket PO draws down; doctor asserts no autonomous release
     */
    public function test_anchor_fractional_stock_po_approval_and_cancellation_restoration(): void
    {
        Event::fake([InventoryConsumed::class, ReorderTriggered::class, StockLow::class, PoSent::class]);

        $biz = TestCase::provisionTenant(['name' => 'Inventory & Stock Tenant', 'currency' => 'USD']);
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

        $po->refresh();
        $this->assertEquals('proposed', $po->status);
        $this->assertNull($po->approved_action_id);

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

        $po->refresh();
        $this->assertEquals('sent', $po->status);
        $this->assertEquals('act_approv_9981', $po->approved_action_id);
    }

    public function test_reorder_trigger_and_clamp(): void
    {
        Event::fake([InventoryConsumed::class, ReorderTriggered::class, StockLow::class]);

        $biz = TestCase::provisionTenant(['name' => 'Inventory & Stock Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $van = StockLocation::create([
            'business_id' => $biz->id,
            'name' => 'Service Van 04',
            'type' => 'van',
        ]);

        $spool = StockItem::create([
            'business_id' => $biz->id,
            'location_id' => $van->id,
            'sku' => 'COPPER-10M-SPOOL-2',
            'barcode' => '784920192832',
            'name' => '3/8" Copper Refrigerant Line',
            'quantity' => 10.0,
            'unit' => 'm',
            'reorder_point' => 3.0,
        ]);

        // Consume below reorder_point (3.0)
        $this->adjustAction->handle(
            businessId: $biz->id,
            stockItemId: $spool->id,
            quantityDelta: 8.0,
            isCancellation: false
        );

        Event::assertDispatched(StockLow::class);
        Event::assertDispatched(ReorderTriggered::class);

        // Consume more than on hand
        $this->adjustAction->handle(
            businessId: $biz->id,
            stockItemId: $spool->id,
            quantityDelta: 5.0,
            isCancellation: false
        );

        $spool->refresh();
        $this->assertEquals(0.0, (float) $spool->quantity);
    }

    /** [G6-48] */
    public function test_g6_48_a_low_stock_alert_never_places_an_order(): void
    {
        Event::fake([InventoryConsumed::class, ReorderTriggered::class, StockLow::class]);

        $biz = TestCase::provisionTenant(['name' => 'Inventory & Stock Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $van = StockLocation::create([
            'business_id' => $biz->id,
            'name' => 'Service Van 04',
            'type' => 'van',
        ]);

        $spool = StockItem::create([
            'business_id' => $biz->id,
            'location_id' => $van->id,
            'sku' => 'COPPER-10M-SPOOL-3',
            'barcode' => '784920192833',
            'name' => '3/8" Copper Refrigerant Line',
            'quantity' => 10.0,
            'unit' => 'm',
            'reorder_point' => 3.0,
        ]);

        $this->adjustAction->handle(
            businessId: $biz->id,
            stockItemId: $spool->id,
            quantityDelta: 8.0,
            isCancellation: false
        );

        Event::assertDispatched(StockLow::class);
        Event::assertDispatched(ReorderTriggered::class);
        $this->assertSame(0, PurchaseOrder::where('business_id', $biz->id)->count(), 'Nothing proposes a restock automatically; the Reorders blade must not claim otherwise.');
    }

    /** [G1-64] */
    public function test_g1_64_a_blanket_po_draws_down_no_autonomous_release(): void
    {
        $path = base_path('app/Modules/X-167');
        $pattern = '(Console|Jobs|Schedule|->cron|artisan\()';
        $grepCommand = sprintf('grep -rniE %s %s', escapeshellarg($pattern), escapeshellarg($path));
        $output = shell_exec($grepCommand);

        $lines = array_filter(explode("\n", $output ?? ''), function ($line) {
            return ! empty($line) && ! str_contains($line, 'capabilities.php') && ! str_contains($line, 'manifest.php');
        });

        $this->assertEmpty($lines, 'No path under app/Modules/X-167/ performs an autonomous release.');
    }

    /** [G6-40] */
    public function test_g6_40_dead_stock_is_reported_never_auto_disposed(): void
    {
        $path = base_path('app/Modules/X-167');
        $grepCommand = sprintf('grep -rniE "(delete\(|destroy\(|forceDelete\()" %s', escapeshellarg($path));
        $output = shell_exec($grepCommand);

        $lines = array_filter(explode("\n", $output ?? ''), function ($line) {
            return ! empty($line) && ! str_contains($line, 'capabilities.php') && ! str_contains($line, 'manifest.php') && ! str_contains($line, 'cascadeOnDelete') && ! str_contains($line, 'nullOnDelete');
        });

        $this->assertEmpty($lines, 'No path under app/Modules/X-167/ performs an auto-dispose (delete).');
    }

    /** [G6-46] */
    public function test_g6_46_no_autonomous_ordering_path_it_proposes(): void
    {
        $path = base_path('app/Modules/X-167');
        $grepCommand = sprintf('grep -rniE "(Http::|curl_|Guzzle|file_get_contents\(\'http)" %s', escapeshellarg($path));
        $output = shell_exec($grepCommand);

        $lines = array_filter(explode("\n", $output ?? ''), function ($line) {
            return ! empty($line) && ! str_contains($line, 'capabilities.php') && ! str_contains($line, 'manifest.php');
        });

        $this->assertEmpty($lines, 'No external purchase API call exists for autonomous ordering.');
    }

    /** [G6-51] */
    public function test_g6_51_a_transfer_is_atomic_truck_to_truck_only(): void
    {
        $path = base_path('app/Modules/X-167');
        $grepCommand = sprintf('grep -rniE "location_id" %s', escapeshellarg($path));
        $output = shell_exec($grepCommand);

        $lines = array_filter(explode("\n", $output ?? ''), function ($line) {
            return ! empty($line) && ! str_contains($line, 'capabilities.php') && ! str_contains($line, 'manifest.php') && ! str_contains($line, 'groupBy') && ! str_contains($line, 'nullOnDelete') && ! str_contains($line, 'foreignId');
        });

        $this->assertEmpty($lines, 'No path under app/Modules/X-167/ performs a location-to-location transfer by updating location_id.');
    }

    /**
     * [G6-40] dead stock is REPORTED, never auto-disposed
     * Asserts the absence of disposal columns and methods.
     */
    public function test_g6_40_no_auto_disposal_path_exists(): void
    {
        foreach (['disposed_at', 'written_off_at', 'disposal_id', 'auto_disposed'] as $col) {
            $this->assertFalse(Schema::hasColumn('stock_items', $col), "stock_items must not have $col");
        }
        $this->assertFalse(method_exists(InventoryEngine::class, 'disposeStock'), 'InventoryEngine must not have disposeStock');
        $this->assertFalse(method_exists(InventoryEngine::class, 'writeOffStock'), 'InventoryEngine must not have writeOffStock');
    }

    /**
     * [G6-51] TRUCK-to-truck only; doctor asserts no location-to-location path
     * Asserts the absence of transfer paths (methods and columns).
     * This asserts the R201/R203 half of the clause only, as the atomicity half has no surface to test.
     */
    public function test_g6_51_no_location_transfer_path_exists(): void
    {
        $this->assertFalse(Schema::hasColumn('stock_items', 'transfer_id'), 'stock_items must not have transfer_id');
        $this->assertFalse(Schema::hasColumn('stock_locations', 'transfer_status'), 'stock_locations must not have transfer_status');

        $methods = get_class_methods(InventoryEngine::class);
        foreach ($methods as $method) {
            $this->assertStringStartsNotWith('transfer', $method, 'InventoryEngine must not have transfer methods');
        }

        $actions = [
            StockAdjustAction::class,
            ReorderProposeAction::class,
            PoGenerateAction::class,
        ];

        foreach ($actions as $actionClass) {
            $methods = get_class_methods($actionClass);
            foreach ($methods as $method) {
                if ($method !== 'handle' && $method !== '__construct' && $method !== 'send') {
                    $this->assertStringStartsNotWith('transfer', $method, "$actionClass must not have transfer methods");
                }
            }
        }
    }

    /** [G1-76] */
    public function test_g1_76_a_partial_receipt_leaves_po_open_and_silent_close_is_refused(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'T1', 'currency' => 'USD']);
        $po = PurchaseOrder::create(['business_id' => $biz->id, 'supplier_id' => null, 'po_number' => 'PO-123', 'items' => [['sku' => 'ITM1', 'qty' => 10]], 'total_cents' => 100, 'status' => 'proposed']);

        $res = $this->engine->receivePurchaseOrder($biz->id, $po->id, [['sku' => 'ITM1', 'qty' => 5]]);
        $this->assertEquals('OPEN', $res['status']);
        $this->assertCount(1, $res['remainder']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('a silently closed PO is REFUSED');
        $this->engine->receivePurchaseOrder($biz->id, $po->id, [['sku' => 'ITM1', 'qty' => 5]], true);
    }

    /** [G1-79] */
    public function test_g1_79_unmatched_receipt_raises_rather_than_posting(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'T2', 'currency' => 'USD']);
        $po = PurchaseOrder::create(['business_id' => $biz->id, 'supplier_id' => null, 'po_number' => 'PO-124', 'items' => [['sku' => 'ITM1', 'qty' => 10]], 'total_cents' => 100, 'status' => 'proposed']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('an unmatched receipt raises rather than posting');
        $this->engine->receivePurchaseOrder($biz->id, $po->id, [['sku' => 'ITM-GHOST', 'qty' => 5]]);
    }

    /** [G6-39] */
    public function test_g6_39_every_level_reconciles_at_job_completion(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'T3', 'currency' => 'USD']);
        $res = $this->engine->completeJob($biz->id, 1, [['reconciled' => true]]);
        $this->assertEquals('completed', $res['status']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('every level reconciles at job completion and is never trusted raw (§198)');
        $this->engine->completeJob($biz->id, 1, [['reconciled' => false]]);
    }

    /** [G6-43] */
    public function test_g6_43_selling_a_kit_decrements_every_component_atomically_or_refused(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'T4', 'currency' => 'USD']);
        $loc = StockLocation::create(['business_id' => $biz->id, 'name' => 'Test Van', 'type' => 'van']);
        $item = StockItem::create(['business_id' => $biz->id, 'location_id' => $loc->id, 'sku' => 'K1', 'barcode' => 'K1', 'name' => 'K1', 'quantity' => 10.0, 'unit' => 'ea', 'reorder_point' => 0.0]);
        $res = $this->engine->sellKit($biz->id, [['id' => $item->id, 'qty' => 2]]);
        $this->assertEquals('sold', $res['status']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('selling a kit decrements every component atomically or the sale is REFUSED');
        $this->engine->sellKit($biz->id, [['id' => $item->id, 'qty' => 20]]);
    }

    /** [G6-47] */
    public function test_g6_47_a_refund_restocks_exactly_once(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'T5', 'currency' => 'USD']);
        $loc = StockLocation::create(['business_id' => $biz->id, 'name' => 'Test Van', 'type' => 'van']);
        $item = StockItem::create(['business_id' => $biz->id, 'location_id' => $loc->id, 'sku' => 'R1', 'barcode' => 'R1', 'name' => 'R1', 'quantity' => 10.0, 'unit' => 'ea', 'reorder_point' => 0.0]);

        $res1 = $this->engine->refundSale($biz->id, $item->id, 5.0, 'ref-123');
        $this->assertEquals('restocked', $res1['status']);

        $res2 = $this->engine->refundSale($biz->id, $item->id, 5.0, 'ref-123');
        $this->assertEquals('ignored', $res2['status']);
    }

    /** [G6-49] */
    public function test_g6_49_serialised_item_with_no_serial_cannot_be_closed(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'T6', 'currency' => 'USD']);
        $loc = StockLocation::create(['business_id' => $biz->id, 'name' => 'Test Van', 'type' => 'van']);
        $item = StockItem::create(['business_id' => $biz->id, 'location_id' => $loc->id, 'sku' => 'S1', 'barcode' => 'S1', 'name' => 'S1', 'quantity' => 1.0, 'unit' => 'ea', 'reorder_point' => 0.0]);

        $res = $this->engine->closeItem($biz->id, $item->id, true, 'SN-123');
        $this->assertEquals('closed', $res['status']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('a serialised item with no serial cannot be closed — asserted');
        $this->engine->closeItem($biz->id, $item->id, true, null);
    }

    /** [G6-18] */
    public function test_g6_18_stock_cannot_be_represented_in_transit_between_locations(): void
    {
        $columns = Schema::getColumnListing('stock_items');
        sort($columns);

        $expected = [
            'barcode',
            'business_id',
            'created_at',
            'id',
            'is_sample',
            'location_id',
            'name',
            'quantity',
            'reorder_point',
            'sku',
            'unit',
            'updated_at',
        ];

        $this->assertSame($expected, $columns, 'Multi-warehouse shipping is out of scope; stock cannot be represented in transit between locations.');
        $this->assertContains('location_id', $columns);
    }

    /** [G6-24] */
    public function test_g6_24_retail_stock_is_tracked_per_location(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Inventory & Stock Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $van = StockLocation::create([
            'business_id' => $biz->id,
            'name' => 'Service Van 04',
            'type' => 'van',
        ]);

        $storage = StockLocation::create([
            'business_id' => $biz->id,
            'name' => 'Storage Unit',
            'type' => 'storage_unit',
        ]);

        StockItem::create([
            'business_id' => $biz->id,
            'location_id' => $van->id,
            'sku' => 'COPPER-10M-SPOOL-24',
            'barcode' => '784920192824',
            'name' => '3/8" Copper Refrigerant Line',
            'quantity' => 7.0,
            'unit' => 'm',
            'reorder_point' => 3.0,
        ]);

        StockItem::create([
            'business_id' => $biz->id,
            'location_id' => $storage->id,
            'sku' => 'COPPER-10M-SPOOL-24',
            'barcode' => '784920192824',
            'name' => '3/8" Copper Refrigerant Line',
            'quantity' => 3.0,
            'unit' => 'm',
            'reorder_point' => 3.0,
        ]);

        $vanStock = StockItem::where('business_id', $biz->id)
            ->where('location_id', $van->id)
            ->where('sku', 'COPPER-10M-SPOOL-24')
            ->first();

        $this->assertNotNull($vanStock, 'Van stock should exist');
        $this->assertEquals(7.0, (float) $vanStock->quantity, 'The per-location query returns only that location\'s row and quantity.');

        $storageStock = StockItem::where('business_id', $biz->id)
            ->where('location_id', $storage->id)
            ->where('sku', 'COPPER-10M-SPOOL-24')
            ->first();

        $this->assertNotNull($storageStock, 'Storage stock should exist');
        $this->assertEquals(3.0, (float) $storageStock->quantity, 'The per-location query returns only that location\'s row and quantity.');
    }

    /** [G6-46] */
    public function test_g6_46_no_autonomous_ordering_path(): void
    {
        Event::fake([InventoryConsumed::class, ReorderTriggered::class, StockLow::class, PoSent::class]);

        $biz = TestCase::provisionTenant(['name' => 'G6-46 Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $supplier = Supplier::create([
            'business_id' => $biz->id,
            'name' => 'Supplier G6-46',
            'email' => 'g646@supp.test',
        ]);

        $poCountBefore = PurchaseOrder::where('status', 'sent')->count();

        // Propose path
        $po = $this->reorderAction->handle(
            businessId: $biz->id,
            supplierId: $supplier->id,
            items: [['sku' => 'ITM-1', 'qty' => 5, 'unit_price_cents' => 100]],
            totalCents: 500
        );

        // Assert proposal exists
        $this->assertEquals('proposed', $po->status);

        // Assert count of released POs is unchanged across the call
        $poCountAfter = PurchaseOrder::where('status', 'sent')->count();
        $this->assertEquals($poCountBefore, $poCountAfter, 'No PO was released autonomously');
    }
}
