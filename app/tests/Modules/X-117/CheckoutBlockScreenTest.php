<?php

declare(strict_types=1);

namespace Tests\Modules\X117;

use App\Modules\X117\Actions\CartAddAction;
use App\Modules\X117\Models\Cart;
use App\Modules\X117\Models\Order;
use App\Modules\X117\Models\OrderLine;
use App\Modules\X117\Models\Sellable;
use App\Modules\X117\Ui\CheckoutBlock;
use App\Modules\X198\Domain\GatewayEngine;
use App\Modules\X198\Domain\StripeGatewayClient;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class CheckoutBlockScreenTest extends TestCase
{
    public function test_checkout_block_takes_a_fresh_authorisation_once_moves_stock_at_paid_and_reverses_on_cancel()
    {
        $bizB = self::provisionTenant();
        Tenancy::set($bizB->id);
        Sellable::create(['business_id' => $bizB->id, 'name' => 'B Item', 'sku' => 'B-1', 'inventory_quantity' => 5, 'unit_price_cents' => 1000, 'fulfilment_type' => 'physical']);
        (new CartAddAction)->handle($bizB->id, 'sess_b', Sellable::first()->id);

        $biz = self::provisionTenant();
        Tenancy::set($biz->id);
        $filter = Sellable::create(['business_id' => $biz->id, 'name' => 'Limited filter', 'sku' => 'FLT-1', 'inventory_quantity' => 1, 'unit_price_cents' => 4500, 'fulfilment_type' => 'physical']);
        $boiler = Sellable::create(['business_id' => $biz->id, 'name' => 'Boiler service', 'sku' => 'BOI-1', 'inventory_quantity' => 10, 'unit_price_cents' => 12000, 'fulfilment_type' => 'service']);

        $addAction = new CartAddAction;
        $addAction->handle($biz->id, 'sess_1', $filter->id);
        $addAction->handle($biz->id, 'sess_1', $boiler->id, 2);

        Tenancy::forget();
        Livewire::test(CheckoutBlock::class, ['sessionToken' => 'sess_1'])->assertForbidden();
        Tenancy::set($biz->id);

        $screen = Livewire::test(CheckoutBlock::class, ['sessionToken' => 'sess_1'])
            ->assertOk()
            ->assertSee('Limited filter')
            ->assertSee('285.00')
            ->assertSee('Reserved until')
            ->assertSee('No orders yet');

        $screen->call('pay')
            ->assertSee('This order needs a fresh authorisation')
            ->assertSee('nothing is charged here in any case: this checkout is waiting on a card-entry surface');

        $this->assertSame(1, $filter->fresh()->inventory_quantity);

        $screen->call('authorise')
            ->assertSee('waiting on a card-entry surface that is not connected yet');

        $token = $screen->get('authToken');

        $screen->call('pay')
            ->assertSee('Pending — order ORD-');

        $this->assertSame(0, $filter->fresh()->inventory_quantity);
        $this->assertSame(8, $boiler->fresh()->inventory_quantity);
        $this->assertSame(1, Order::where('business_id', $biz->id)->where('auth_token', $token)->count());
        $this->assertSame(2, OrderLine::where('business_id', $biz->id)->count());
        $this->assertSame(0, Cart::where('business_id', $biz->id)->where('session_token', 'sess_1')->count());

        $addAction->handle($biz->id, 'sess_1', $boiler->id, 1);

        $screen = Livewire::test(CheckoutBlock::class, ['sessionToken' => 'sess_1']);
        $screen->set('authToken', $token)
            ->call('pay')
            ->assertSee('needs a fresh authorisation');

        $this->assertSame(8, $boiler->fresh()->inventory_quantity);
        $this->assertSame(1, Order::where('business_id', $biz->id)->count());

        Sellable::whereKey($boiler->id)->update(['inventory_quantity' => 0]);
        $screen->call('authorise')
            ->call('pay')
            ->assertSee('Boiler service is sold out');

        $this->assertSame(1, Order::where('business_id', $biz->id)->count());

        $orderId = Order::where('business_id', $biz->id)->first()->id;
        $screen->call('cancel', $orderId)
            ->assertSee('cancelled; its stock is back on the shelf')
            ->assertDontSee('§147.2');

        $this->assertSame(1, $filter->fresh()->inventory_quantity);
        $this->assertSame(2, $boiler->fresh()->inventory_quantity);
        $this->assertSame('cancelled', Order::whereKey($orderId)->first()->status);

        Tenancy::set($bizB->id);
        $this->assertSame(1, Cart::where('business_id', $bizB->id)->count());
        $this->assertSame(0, Order::where('business_id', $bizB->id)->count());
    }

    public function test_merchant_connection_does_not_trigger_gateway_call()
    {
        $biz = self::provisionTenant();
        Tenancy::set($biz->id);

        app(GatewayEngine::class)->connect($biz->id, 'stripe', 'acct_test_x117');

        $client = new class
        {
            public int $calls = 0;

            public function charge(int $amountCents, string $source, string $currency = 'USD'): string
            {
                $this->calls++;
                throw new \RuntimeException('Stripe client should not be called.');
            }
        };

        $this->app->instance(StripeGatewayClient::class, $client);

        $filter = Sellable::create(['business_id' => $biz->id, 'name' => 'Test Item', 'sku' => 'TEST-1', 'inventory_quantity' => 1, 'unit_price_cents' => 1000, 'fulfilment_type' => 'physical']);
        (new CartAddAction)->handle($biz->id, 'sess_x', $filter->id);

        $screen = Livewire::test(CheckoutBlock::class, ['sessionToken' => 'sess_x']);
        $screen->call('authorise');
        $screen->call('pay')
            ->assertSee('waiting on a card-entry surface');

        $this->assertSame(0, $client->calls);

        $order = Order::where('business_id', $biz->id)->first();
        $this->assertNotNull($order);
        $this->assertSame('pending_payment', $order->status);
    }
}
