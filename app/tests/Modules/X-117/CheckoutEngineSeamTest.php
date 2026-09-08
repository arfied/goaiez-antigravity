<?php

declare(strict_types=1);

namespace Tests\Modules\X117;

use App\Modules\X117\Actions\CartAddAction;
use App\Modules\X117\Domain\CheckoutEngine;
use App\Modules\X117\Events\CartCheckedOut;
use App\Modules\X117\Models\Sellable;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutEngineSeamTest extends TestCase
{
    public function test_the_checkout_seam_announces_the_order_and_carries_no_payment_instrument()
    {
        $biz = self::provisionTenant();
        Tenancy::set($biz->id);

        $sellable = Sellable::create([
            'business_id' => $biz->id,
            'name' => 'B Item',
            'sku' => 'B-1',
            'inventory_quantity' => 5,
            'unit_price_cents' => 1000,
            'fulfilment_type' => 'physical',
        ]);

        (new CartAddAction)->handle($biz->id, 'sess_test', $sellable->id);

        Event::fake([CartCheckedOut::class]);

        $freshAuthToken = 'auth_'.Str::random(20);

        $engine = app(CheckoutEngine::class);
        $res = $engine->checkoutCart($biz->id, 'sess_test', $freshAuthToken, null);

        Event::assertDispatched(CartCheckedOut::class, function ($e) use ($res) {
            $this->assertFalse(property_exists($e, 'authToken'));

            return $e->orderId === $res['order_id'];
        });
    }
}
