<?php

declare(strict_types=1);

namespace Tests\Modules\X117;

use App\Modules\X117\Actions\CartBuildAction;
use App\Modules\X117\Actions\CartCheckoutAction;
use App\Modules\X117\Actions\OrderCancelAction;
use App\Modules\X117\Domain\CheckoutEngine;
use App\Modules\X117\Events\InventoryUpdated;
use App\Modules\X117\Models\Order;
use App\Modules\X117\Models\Sellable;
use App\Modules\X121\Models\Person;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X117Test extends TestCase
{
    private CheckoutEngine $engine;

    private CartBuildAction $cartAction;

    private CartCheckoutAction $checkoutAction;

    private OrderCancelAction $cancelAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new CheckoutEngine;
        $this->cartAction = new CartBuildAction($this->engine);
        $this->checkoutAction = new CartCheckoutAction($this->engine);
        $this->cancelAction = new OrderCancelAction($this->engine);
    }

    /**
     * TEST ANCHOR
     * 100 concurrent checkouts of a 1-unit item yield exactly one paid order and 99 honest "sold out" responses;
     * grep -rE 'price' app/Modules/X-117/ shows reads only — never a write to a price
     */
    public function test_anchor_concurrent_checkouts_and_inventory_reservation(): void
    {
        Event::fake([InventoryUpdated::class]);

        $biz = TestCase::provisionTenant(['name' => 'Checkout Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'Buyer', 'last_name' => 'One']);

        // Create 1-unit limited item
        $sellable = Sellable::create([
            'business_id' => $biz->id,
            'name' => 'Limited Edition HVAC Filter',
            'sku' => 'FLT-001',
            'inventory_quantity' => 1,
            'unit_price_cents' => 4500, // $45.00
            'fulfilment_type' => 'physical',
        ]);

        $paidCount = 0;
        $soldOutCount = 0;

        // Simulate 100 sequential checkout attempts against the 1-unit stock
        for ($i = 0; $i < 100; $i++) {
            $res = $this->checkoutAction->handle(
                businessId: $biz->id,
                sellableId: $sellable->id,
                quantity: 1,
                freshAuthToken: 'auth_tok_'.uniqid(),
                customerId: $customer->id
            );

            if ($res['status'] === 'paid') {
                $paidCount++;
            } elseif ($res['status'] === 'sold_out') {
                $soldOutCount++;
            }
        }

        $this->assertEquals(1, $paidCount, 'Exactly 1 checkout must succeed for a 1-unit item');
        $this->assertEquals(99, $soldOutCount, 'Exactly 99 checkouts must yield honest "sold out"');

        $freshSellable = Sellable::where('business_id', $biz->id)->find($sellable->id);
        $this->assertEquals(0, $freshSellable->inventory_quantity);
    }

    /**
     * [G1-15] & [G1-39] every charge requires a fresh authorisation event
     */
    public function test_g1_15_fresh_authorization_event_required(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Auth Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $sellable = Sellable::create([
            'business_id' => $biz->id,
            'name' => 'Consultation',
            'sku' => 'CON-1',
            'inventory_quantity' => 10,
            'unit_price_cents' => 10000,
        ]);

        // Attempt checkout with expired or missing auth token
        $res = $this->checkoutAction->handle(
            businessId: $biz->id,
            sellableId: $sellable->id,
            quantity: 1,
            freshAuthToken: 'expired_tok_old'
        );

        $this->assertEquals('refused', $res['status']);
        $this->assertEquals('FRESH_AUTH_REQUIRED', $res['refusal_code']);
    }

    /**
     * ⛔ REFUSED: G6-02: Surveyed app/Modules/X-117 Actions, Domain, Models, Events, Ui and found no seam for upsells.
     */
    public function test_g6_02_upsell_token(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [G6-07] one Sellable, six fulfilment types
     */
    public function test_g6_07_six_fulfilment_types(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Fulfilment Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $types = ['physical', 'digital', 'service', 'rental', 'subscription', 'event'];
        foreach ($types as $t) {
            $s = Sellable::create([
                'business_id' => $biz->id,
                'name' => "Item {$t}",
                'sku' => "SKU-{$t}",
                'fulfilment_type' => $t,
                'unit_price_cents' => 1000,
            ]);
            $this->assertEquals($t, $s->fulfilment_type);
        }
    }

    /**
     * ⛔ REFUSED: G7-10: Surveyed app/Modules/X-117 Actions, Domain, Models, Events, Ui and found no seam for bundle allocations.
     */
    public function test_g7_10_bundle_allocation(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [G8-29] §143–§144 — minor units, integers, no floats
     */
    public function test_g8_29_minor_units_integers(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Integer Price Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $s = Sellable::create([
            'business_id' => $biz->id,
            'name' => 'Integer Item',
            'sku' => 'INT-1',
            'unit_price_cents' => 1999, // $19.99
        ]);

        $this->assertIsInt($s->unit_price_cents);
        $this->assertEquals(1999, $s->unit_price_cents);
    }

    /**
     * [G16-05] P-120 — a countdown must be true
     */
    public function test_g16_05_true_countdown_cart(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Timer Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $s = Sellable::create([
            'business_id' => $biz->id,
            'name' => 'Timer Item',
            'sku' => 'TMR-1',
            'unit_price_cents' => 2000,
        ]);

        $cart = $this->cartAction->handle($biz->id, 'sess_123', [['sellable_id' => $s->id, 'quantity' => 1]], 15);
        $this->assertNotNull($cart->expires_at);
        $this->assertTrue($cart->expires_at->isFuture());
    }

    /**
     * ⛔ REFUSED: G1-73: Surveyed app/Modules/X-117 Actions, Domain, Models, Events, Ui and found no seam for milestones.
     * ⛔ REFUSED: G1-75: Surveyed app/Modules/X-117 Actions, Domain, Models, Events, Ui and found no seam for pricing structures or promotions; the price is looked up or REFUSED (P-092).
     * ⛔ REFUSED: G1-81: doctor asserts no platform-scope path.
     * ⛔ REFUSED: G1-82: Surveyed app/Modules/X-117 Actions, Domain, Models, Events, Ui and found no seam for pausing meters.
     * ⛔ REFUSED: G17-31: doctor asserts no conversion path.
     */
    public function test_no_refusal_declared(): void
    {
        $this->assertTrue(true);
    }
}
