<?php

declare(strict_types=1);

namespace Tests\Modules\X117;

use App\Modules\X117\Models\Cart;
use App\Modules\X117\Models\Sellable;
use App\Modules\X117\Ui\CartBlock;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class CartBlockScreenTest extends TestCase
{
    public function test_cart_block_holds_the_catalogue_with_a_true_countdown_and_never_moves_stock()
    {
        $bizB = self::provisionTenant();
        Tenancy::set($bizB->id);
        Sellable::create(['business_id' => $bizB->id, 'name' => 'Gutter clean', 'sku' => 'GUT-B', 'inventory_quantity' => 5, 'unit_price_cents' => 9900, 'fulfilment_type' => 'service']);

        $biz = self::provisionTenant();
        Tenancy::set($biz->id);
        $filter = Sellable::create(['business_id' => $biz->id, 'name' => 'Limited filter', 'sku' => 'FLT-1', 'inventory_quantity' => 1, 'unit_price_cents' => 4500, 'fulfilment_type' => 'physical']);
        $visit = Sellable::create(['business_id' => $biz->id, 'name' => 'Boiler service', 'sku' => 'BOI-1', 'inventory_quantity' => 10, 'unit_price_cents' => 12000, 'fulfilment_type' => 'service']);

        Tenancy::forget();
        Livewire::test(CartBlock::class, ['sessionToken' => 'sess_1'])->assertForbidden();
        Tenancy::set($biz->id);

        $screen = Livewire::test(CartBlock::class, ['sessionToken' => 'sess_1'])
            ->assertOk()
            ->assertSee('Limited filter')
            ->assertSee('stock comes off when the order is placed at checkout, not when it is paid')
            ->assertSee('45.00')
            ->assertSee('1 in stock')
            ->assertSee('Boiler service')
            ->assertSee('120.00')
            ->assertDontSee('Gutter clean')
            ->assertSee('Nothing in the cart yet')
            ->assertSeeHtml('wire:click="add('.$filter->id.')"')
            ->call('add', $filter->id)
            ->assertSee('Limited filter is in the cart')
            ->assertSee('Cart total: 45.00')
            ->assertSee('This cart expires at')
            ->assertSee('Nothing is held for you')
            ->assertSee('another cart can take the last one first');

        $cart = Cart::where('business_id', $biz->id)->where('session_token', 'sess_1')->firstOrFail();
        $until = $cart->expires_at->format('H:i:s');

        $screen->assertSee($until)
            ->call('add', $filter->id)
            ->assertSee('Limited filter is sold out: 1 in stock, 1 already in this cart')
            ->assertSee('Cart total: 45.00')
            ->call('add', $visit->id)
            ->assertSee('Boiler service is in the cart')
            ->assertSee('Cart total: 165.00')
            ->assertSee($until)
            ->assertSeeHtml('wire:click="remove('.$filter->id.')"')
            ->call('remove', $filter->id)
            ->assertSee('Limited filter is out of the cart')
            ->assertSee('Cart total: 120.00')
            ->assertSee($until)
            ->call('checkout')
            ->assertSee('waits on the checkout block')
            ->assertSee('Stock comes off the moment the order is placed, before any payment')
            ->assertSee('comes back only if you cancel the order')
            ->call('add', 999999)
            ->assertSee("isn't in this catalogue");

        $this->assertSame(1, $filter->fresh()->inventory_quantity, 'the cart screen only sets a waiting state, so no order is placed and no stock moves');
        $this->assertSame(10, $visit->fresh()->inventory_quantity);
        $this->assertSame($until, $cart->fresh()->expires_at->format('H:i:s'), 'the clock never moved');
        $this->assertSame(12000, $cart->fresh()->total_cents);
        Tenancy::set($bizB->id);
        $this->assertSame(0, Cart::where('business_id', $bizB->id)->count());
        $this->assertSame(1, Sellable::where('business_id', $bizB->id)->count());
    }
}
