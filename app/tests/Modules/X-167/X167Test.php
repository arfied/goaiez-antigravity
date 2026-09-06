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
        $this->assertSame(0, PurchaseOrder::where('business_id', $biz->id)->count());
    }

    /** [G1-64] */
    public function test_g1_64_a_blanket_po_draws_down_no_autonomous_release(): void
    {
        $path = base_path('app/Modules/X-167');
        $grepCommand = sprintf('grep -rniE "(Console|Jobs\\\\\\\\|Schedule|->cron|artisan\()" %s', escapeshellarg($path));
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
}
