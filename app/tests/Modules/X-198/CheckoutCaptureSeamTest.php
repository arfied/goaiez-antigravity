<?php

declare(strict_types=1);

namespace Tests\Modules\X198;

use App\Modules\X117\Actions\CartAddAction;
use App\Modules\X117\Actions\CartPayAction;
use App\Modules\X117\Models\Order;
use App\Modules\X117\Models\Sellable;
use App\Modules\X198\Domain\GatewayEngine;
use App\Modules\X198\Domain\StripeGatewayClient;
use App\Modules\X198\Models\Payment;
use App\Support\Tenancy;
use Tests\TestCase;

class CheckoutCaptureSeamTest extends TestCase
{
    public function test_the_checkout_seam_captures_once_and_waits_when_no_merchant_is_connected(): void
    {
        $biz = self::provisionTenant();
        Tenancy::set($biz->id);

        $sellable = Sellable::create([
            'business_id' => $biz->id,
            'name' => 'Boiler service',
            'sku' => 'BOI-1',
            'inventory_quantity' => 10,
            'unit_price_cents' => 12000,
            'fulfilment_type' => 'service',
        ]);

        app(GatewayEngine::class)->connect($biz->id, 'paypal', 'merch_123');

        app(CartAddAction::class)->handle($biz->id, 'sess_1', $sellable->id, 2);

        $checkoutResult = app(CartPayAction::class)->handle($biz->id, 'sess_1', 'tok_fresh');
        $orderId = $checkoutResult['order_id'];

        $this->assertSame(1, Payment::where('business_id', $biz->id)->count());
        $payment = Payment::where('business_id', $biz->id)->first();
        $this->assertSame(24000, $payment->amount_cents);
        $this->assertSame('x117-order-'.$orderId, $payment->idempotency_key);
        $this->assertNull($payment->gateway_charge_id);

        $biz2 = self::provisionTenant();
        Tenancy::set($biz2->id);

        $sellable2 = Sellable::create([
            'business_id' => $biz2->id,
            'name' => 'Gutter clean',
            'sku' => 'GUT-1',
            'inventory_quantity' => 5,
            'unit_price_cents' => 9900,
            'fulfilment_type' => 'service',
        ]);

        app(CartAddAction::class)->handle($biz2->id, 'sess_2', $sellable2->id, 1);

        $checkoutResult2 = app(CartPayAction::class)->handle($biz2->id, 'sess_2', 'tok_fresh_2');

        $this->assertSame('pending_payment', $checkoutResult2['status']);
        $this->assertSame(0, Payment::where('business_id', $biz2->id)->count());
    }

    public function test_an_unconfirmed_checkout_leaves_the_order_pending(): void
    {
        $biz = self::provisionTenant();
        Tenancy::set($biz->id);

        $sellable = Sellable::create([
            'business_id' => $biz->id,
            'name' => 'Boiler service',
            'sku' => 'BOI-1',
            'inventory_quantity' => 10,
            'unit_price_cents' => 12000,
            'fulfilment_type' => 'service',
        ]);

        app(GatewayEngine::class)->connect($biz->id, 'paypal', 'merch_123');

        app(CartAddAction::class)->handle($biz->id, 'sess_1', $sellable->id, 2);

        $checkoutResult = app(CartPayAction::class)->handle($biz->id, 'sess_1', 'tok_fresh');
        $orderId = $checkoutResult['order_id'];

        $this->assertSame('pending_payment', Order::whereKey($orderId)->first()->status);

        $payment = Payment::where('business_id', $biz->id)->first();
        $this->assertNotNull($payment);
        $this->assertNull($payment->gateway_charge_id);
    }

    public function test_a_confirmed_checkout_promotes_the_order_and_says_so(): void
    {
        $biz = self::provisionTenant();
        Tenancy::set($biz->id);

        $this->app->instance(StripeGatewayClient::class, new class
        {
            public function charge(int $amountCents, string $source, string $currency = 'USD'): string
            {
                return 'ch_stub_money58';
            }
        });

        app(GatewayEngine::class)->connect($biz->id, 'stripe', 'acct_stub');

        $sellable = Sellable::create([
            'business_id' => $biz->id,
            'name' => 'Boiler service',
            'sku' => 'BOI-1',
            'inventory_quantity' => 10,
            'unit_price_cents' => 12000,
            'fulfilment_type' => 'service',
        ]);

        app(CartAddAction::class)->handle($biz->id, 'sess_1', $sellable->id, 2);

        $checkoutResult = app(CartPayAction::class)->handle($biz->id, 'sess_1', 'tok_fresh');
        $orderId = $checkoutResult['order_id'];

        $this->assertSame('ch_stub_money58', Payment::where('business_id', $biz->id)->first()->gateway_charge_id);
        $this->assertSame('paid', Order::whereKey($orderId)->first()->status);
        $this->assertSame('paid', $checkoutResult['status']);
        $this->assertSame(1, Payment::where('business_id', $biz->id)->count());
    }
}
