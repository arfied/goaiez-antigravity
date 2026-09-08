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
    public function test_the_checkout_seam_makes_no_gateway_call_with_or_without_a_merchant(): void
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

        $this->assertSame(0, Payment::where('business_id', $biz->id)->count());
        $this->assertSame('pending_payment', Order::whereKey($orderId)->first()->status);

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
        $this->assertSame(0, Payment::where('business_id', $biz->id)->count());
    }

    public function test_a_connected_stripe_merchant_still_gets_no_gateway_call(): void
    {
        $biz = self::provisionTenant();
        Tenancy::set($biz->id);

        $client = new class
        {
            public int $calls = 0;

            public function charge(int $amountCents, string $source, string $currency = 'USD'): string
            {
                $this->calls++;

                return 'ch_stub_money5812345678901';
            }
        };
        $this->app->instance(StripeGatewayClient::class, $client);

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

        $this->assertSame(0, $client->calls);
        $this->assertSame(0, Payment::where('business_id', $biz->id)->count());
        $this->assertSame('pending_payment', Order::whereKey($orderId)->first()->status);
        $this->assertSame('pending_payment', $checkoutResult['status']);
    }
}
