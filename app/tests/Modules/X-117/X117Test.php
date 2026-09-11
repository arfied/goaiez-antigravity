<?php

declare(strict_types=1);

namespace Tests\Modules\X117;

use App\Modules\X117\Actions\CartBuildAction;
use App\Modules\X117\Actions\CartCheckoutAction;
use App\Modules\X117\Actions\OrderCancelAction;
use App\Modules\X117\Domain\CheckoutEngine;
use App\Modules\X117\Domain\OrderNotCancellableException;
use App\Modules\X117\Events\InventoryUpdated;
use App\Modules\X117\Models\Order;
use App\Modules\X117\Models\OrderLine;
use App\Modules\X117\Models\Sellable;
use App\Modules\X121\Models\Person;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
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
     * 100 sequential checkouts of a 1-unit item yield exactly one pending_payment order and 99 honest "sold out" responses;
     * grep -rE 'price' app/Modules/X-117/ shows reads only — never a write to a price
     */
    public function test_anchor_sequential_checkouts_and_inventory_decrement(): void
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

            if ($res['status'] === 'pending_payment') {
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
            'inventory_quantity' => 10,
            'unit_price_cents' => 1999, // $19.99
            'fulfilment_type' => 'physical',
        ]);

        $res = $this->checkoutAction->handle(
            businessId: $biz->id,
            sellableId: $s->id,
            quantity: 3,
            freshAuthToken: 'auth_tok_'.uniqid()
        );

        $order = Order::findOrFail($res['order_id']);
        $this->assertSame(5997, $order->total_cents);
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

        $this->travelTo(now()->startOfMinute());

        $cart15 = $this->cartAction->handle($biz->id, 'sess_15', [['sellable_id' => $s->id, 'quantity' => 1]], 15);
        $this->assertSame(15, (int) now()->diffInMinutes($cart15->expires_at));

        $cart30 = $this->cartAction->handle($biz->id, 'sess_30', [['sellable_id' => $s->id, 'quantity' => 1]], 30);
        $this->assertSame(30, (int) now()->diffInMinutes($cart30->expires_at));

        $this->assertSame(15, (int) $cart15->expires_at->diffInMinutes($cart30->expires_at));
    }

    public function test_an_order_row_written_without_a_status_is_pending_payment(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Default Status Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $id = DB::table('orders')->insertGetId([
            'business_id' => $biz->id,
            'order_number' => 'ORD-TEST-123',
            'total_cents' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $order = DB::table('orders')->find($id);
        $this->assertEquals('pending_payment', $order->status);
    }

    /** [G18-29] */
    public function test_g18_29_lifecycle_stops_at_money(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Checkout Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $sellable = Sellable::create([
            'business_id' => $biz->id,
            'name' => 'Physical Item',
            'sku' => 'PHY-001',
            'inventory_quantity' => 5,
            'unit_price_cents' => 1500,
            'fulfilment_type' => 'physical',
        ]);

        $res = $this->checkoutAction->handle(
            businessId: $biz->id,
            sellableId: $sellable->id,
            quantity: 1,
            freshAuthToken: 'auth_tok_'.uniqid()
        );

        $this->assertEquals('pending_payment', $res['status']);

        $order = Order::findOrFail($res['order_id']);
        $this->assertEquals('pending_payment', $order->status);

        $orderLine = OrderLine::where('order_id', $order->id)->firstOrFail();

        $this->assertContains($order->status, ['paid', 'cancelled', 'sold_out', 'pending_payment']);

        $this->cancelAction->handle($biz->id, $order->id);
        $order->refresh();
        $this->assertEquals('cancelled', $order->status);

        $allNames = array_merge(
            array_keys($res),
            array_keys($order->getAttributes()),
            array_keys($orderLine->getAttributes()),
            array_keys($sellable->getAttributes())
        );

        foreach ($allNames as $name) {
            $this->assertDoesNotMatchRegularExpression(
                '/(will_call|willcall|pickup|pick_up|driver|courier|shipment|waybill|tracking_number|delivery|dispatched_at)/i',
                $name
            );
        }

        $this->assertContains('status', $allNames);
        $this->assertContains('total_cents', $allNames);
        $this->assertContains('order_number', $allNames);
        $this->assertGreaterThanOrEqual(18, count($allNames));

        $sellableKeys = array_keys($sellable->getAttributes());
        $orderKeys = array_keys($order->getAttributes());
        $orderLineKeys = array_keys($orderLine->getAttributes());

        $this->assertContains('fulfilment_type', $sellableKeys);
        $this->assertNotContains('fulfilment_type', $orderKeys);
        $this->assertNotContains('fulfilment_type', $orderLineKeys);

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path('Modules/X-117')));
        $files = [];
        $controlCount = 0;
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && ! in_array($file->getBasename(), ['capabilities.php', 'manifest.php'])) {
                $files[] = $file->getPathname();
                if (preg_match('/Order|Cart|Sellable|Checkout/', $file->getBasename())) {
                    $controlCount++;
                }
            }
        }

        $this->assertGreaterThanOrEqual(14, count($files));
        $this->assertGreaterThanOrEqual(6, $controlCount);

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression(
                '/\b(will_call|willcall|pickup|pick_up|driver|courier|shipment|waybill|tracking_number|delivery_window|ready_for_pickup)\b/i',
                $content
            );
        }
    }

    /**
     * [G1-75] a pricing STRUCTURE, not a promotion; the price is looked up or REFUSED (P-092)
     */
    public function test_g1_75_price_is_looked_up_or_refused(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Price Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $sellable = Sellable::create([
            'business_id' => $biz->id,
            'name' => 'Consultation',
            'sku' => 'CON-PRICE',
            'inventory_quantity' => 10,
            'unit_price_cents' => 5000,
        ]);

        // (i) Assert the total equals the stored unit_price_cents, ignoring caller input
        $cart = $this->cartAction->handle(
            businessId: $biz->id,
            sessionToken: 'sess_price_1',
            items: [['sellable_id' => $sellable->id, 'quantity' => 2, 'unit_price_cents' => 1000]]
        );

        $this->assertEquals(10000, $cart->total_cents, 'Total must equal stored price * qty (5000 * 2), ignoring input price');

        // (ii) Assert an unknown sellable is refused with ModelNotFoundException
        $this->expectException(ModelNotFoundException::class);
        $this->cartAction->handle(
            businessId: $biz->id,
            sessionToken: 'sess_price_2',
            items: [['sellable_id' => 9999, 'quantity' => 1]]
        );
    }

    /**
     * [G6-02] A post-charge upsell on a card already on file would charge a second time on the
     * authorisation that paid the first order. checkoutCart() refuses that: an authorisation is a
     * one-shot nonce, and the guard is the SECOND check in the method, before the cart is even
     * read. The second cart below is therefore load-bearing — with no cart the method would refuse
     * for a different reason and this test would pass for the wrong one.
     */
    public function test_g6_02_an_authorisation_pays_once_and_a_second_charge_on_it_is_refused(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Upsell Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $sellable = Sellable::create([
            'business_id' => $biz->id,
            'name' => 'Filter',
            'sku' => 'FIL-1',
            'inventory_quantity' => 5,
            'unit_price_cents' => 2500,
        ]);

        $token = 'auth_'.uniqid();

        $this->cartAction->handle($biz->id, 'sess_upsell', [['sellable_id' => $sellable->id, 'quantity' => 1]], 15);
        $first = $this->engine->checkoutCart($biz->id, 'sess_upsell', $token, null);
        $this->assertSame('pending_payment', $first['status']);

        $this->cartAction->handle($biz->id, 'sess_upsell', [['sellable_id' => $sellable->id, 'quantity' => 1]], 15);
        $second = $this->engine->checkoutCart($biz->id, 'sess_upsell', $token, null);

        $this->assertSame('refused', $second['status']);
        $this->assertSame('AUTH_USED', $second['refusal_code']);
        $this->assertStringContainsString('an authorisation pays once and this one already has', $second['message']);
        $this->assertSame(1, Order::where('business_id', $biz->id)->count());
        $this->assertSame(4, $sellable->fresh()->inventory_quantity);
    }

    /**
     * [G17-31] One currency, and no conversion path. Every money value X-117 writes is minor units
     * of the account's single currency: a cart total is the exact integer sum of its lines with no
     * factor applied, and neither the result, the order nor its lines carries a rate or a landed
     * cost. The scan is the shape test_g18_29 already uses on this file's fulfilment vocabulary.
     * It deliberately does NOT refuse a bare `currency` column — naming the currency of a money
     * value is a fix, and converting between two is what this capability rules out.
     */
    public function test_g17_31_one_currency_with_no_conversion_path(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'One Currency Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $cheap = Sellable::create([
            'business_id' => $biz->id,
            'name' => 'Washer',
            'sku' => 'WSH-1',
            'inventory_quantity' => 9,
            'unit_price_cents' => 1500,
        ]);

        $dear = Sellable::create([
            'business_id' => $biz->id,
            'name' => 'Manifold',
            'sku' => 'MAN-1',
            'inventory_quantity' => 9,
            'unit_price_cents' => 2075,
        ]);

        $this->cartAction->handle($biz->id, 'sess_one_currency', [
            ['sellable_id' => $cheap->id, 'quantity' => 2],
            ['sellable_id' => $dear->id, 'quantity' => 3],
        ], 15);

        $res = $this->engine->checkoutCart($biz->id, 'sess_one_currency', 'auth_'.uniqid(), null);

        $this->assertSame('pending_payment', $res['status']);
        $this->assertSame(2 * 1500 + 3 * 2075, $res['total_cents']);

        $order = Order::findOrFail($res['order_id']);
        $this->assertSame(2 * 1500 + 3 * 2075, $order->total_cents);

        $line = OrderLine::where('order_id', $order->id)->firstOrFail();

        $names = array_merge(
            array_keys($res),
            array_keys($order->getAttributes()),
            array_keys($line->getAttributes())
        );

        foreach ($names as $name) {
            $this->assertDoesNotMatchRegularExpression(
                '/(exchange_rate|fx_rate|conversion_rate|converted_|landed_cost)/i',
                $name
            );
        }
    }

    public function test_an_order_already_cancelled_is_refused_and_its_stock_is_not_returned_twice(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Cancel Test', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");
        $businessId = $biz->id;

        $order = Order::create([
            'business_id' => $businessId,
            'customer_id' => null,
            'order_number' => 'ORD-TEST',
            'status' => 'pending_payment',
            'total_cents' => 1500,
            'auth_token' => 'auth_token',
        ]);

        $sellable = Sellable::create([
            'business_id' => $businessId,
            'name' => 'Item',
            'sku' => 'ITEM-01',
            'unit_price_cents' => 1500,
            'inventory_quantity' => 10,
        ]);

        OrderLine::create([
            'business_id' => $businessId,
            'order_id' => $order->id,
            'sellable_id' => $sellable->id,
            'quantity' => 1,
            'subtotal_cents' => 1500,
        ]);

        $this->cancelAction->handle($businessId, $order->id);

        $sellable->refresh();
        $this->assertSame(11, $sellable->inventory_quantity);

        try {
            $this->cancelAction->handle($businessId, $order->id);
            $this->fail('A second cancel was accepted: cancelOrder has no status guard.');
        } catch (OrderNotCancellableException $e) {
            $sellable->refresh();
            $this->assertSame(11, $sellable->inventory_quantity);
        }
    }

    public function test_a_colliding_order_number_is_re_minted_and_the_checkout_still_lands(): void
    {
        // Str::random draws from 62 symbols and strtoupper collapses them to 36 non-uniformly, so
        // order numbers collide by birthday at ~38k orders. The sequence seam forces the first draw
        // to collide with a number this tenant already holds; the second draw must be taken.
        $biz = TestCase::provisionTenant(['name' => 'OrderNumberCollision', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // …seed a Sellable with stock, build a cart through the engine's own API, and seed an
        // existing Order on this business whose order_number is 'ORD-TAKEN1'…
        $sellable = Sellable::create([
            'business_id' => $biz->id,
            'name' => 'Collision Item',
            'sku' => 'COL-1',
            'inventory_quantity' => 10,
            'unit_price_cents' => 1500,
        ]);

        Order::create([
            'business_id' => $biz->id,
            'customer_id' => null,
            'order_number' => 'ORD-TAKEN1',
            'status' => 'pending_payment',
            'total_cents' => 1500,
            'auth_token' => 'auth_token_taken',
        ]);

        $sessionToken = 'sess_collision';
        $this->cartAction->handle($biz->id, $sessionToken, [['sellable_id' => $sellable->id, 'quantity' => 1]], 15);

        Str::createRandomStringsUsingSequence(['TAKEN1', 'FRESH2']);

        $res = app(CheckoutEngine::class)->checkoutCart($biz->id, $sessionToken, 'auth_fresh_token');

        Str::createRandomStringsNormally();

        // The order that landed carries the SECOND draw, not the first and not the taken one.
        $this->assertSame('ORD-FRESH2', $res['order_number']);

        // And there is exactly one order under the taken number — the pre-existing one.
        $this->assertSame(
            1,
            Order::where('business_id', $biz->id)->where('order_number', 'ORD-TAKEN1')->count()
        );
    }
}
