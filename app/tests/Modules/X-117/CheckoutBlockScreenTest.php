<?php

declare(strict_types=1);

namespace Tests\Modules\X117;

use App\Modules\X117\Models\Cart;
use App\Modules\X117\Models\Sellable;
use App\Modules\X117\Ui\CheckoutBlock;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class CheckoutBlockScreenTest extends TestCase
{
    public function test_checkout_block_authorise_pay_cancel()
    {
        $biz = self::provisionTenant();
        Tenancy::set($biz->id);

        $sellable = Sellable::create([
            'business_id' => $biz->id,
            'name' => 'Widget',
            'sku' => 'WID-1',
            'inventory_quantity' => 10,
            'unit_price_cents' => 5000,
            'fulfilment_type' => 'physical'
        ]);

        Cart::create([
            'business_id' => $biz->id,
            'session_token' => 'sess_2',
            'items' => [['sellable_id' => $sellable->id, 'quantity' => 2]],
            'total_cents' => 10000,
            'expires_at' => now()->addMinutes(15)
        ]);

        Livewire::test(CheckoutBlock::class, ['sessionToken' => 'sess_2'])
            ->assertOk()
            ->assertSee('Authorise')
            ->assertSee('Pay')
            ->assertSee('Cancel')
            ->assertDontSee('Card Number')
            ->call('authorise')
            ->assertSee('Authorised')
            ->call('cancel')
            ->assertSee('Cancelled')
            ->call('authorise')
            ->call('pay')
            ->assertSee('Paid 100.00')
            ->call('pay')
            ->assertSee('needs a fresh authorisation');

        $this->assertSame(8, $sellable->fresh()->inventory_quantity);
    }
}
