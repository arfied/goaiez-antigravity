<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X117\Models\Sellable;
use App\Modules\X117\Models\Cart;
use App\Modules\X117\Models\Order;

class X117Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-117';
    }

    public function fill(Business $business): int
    {
        if (Sellable::where('business_id', $business->id)->where('name', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $s1 = Sellable::create([
            'business_id' => $business->id,
            'name' => self::MARKER . 'Furnace Tune-up',
            'fulfilment_type' => 'service',
            'inventory_quantity' => 10,
            'unit_price_cents' => 9900,
        ]);

        $s2 = Sellable::create([
            'business_id' => $business->id,
            'name' => self::MARKER . 'Air Filter (16x25x1)',
            'fulfilment_type' => 'product',
            'inventory_quantity' => 50,
            'unit_price_cents' => 1500,
        ]);

        $s3 = Sellable::create([
            'business_id' => $business->id,
            'name' => self::MARKER . 'Thermostat Installation',
            'fulfilment_type' => 'service',
            'inventory_quantity' => 0, // sold out
            'unit_price_cents' => 15000,
        ]);

        Cart::create([
            'business_id' => $business->id,
            'session_token' => 'demo_session_1',
            'items' => [
                ['sellable_id' => $s1->id, 'quantity' => 1],
                ['sellable_id' => $s2->id, 'quantity' => 2],
            ],
            'total_cents' => 12900,
            'expires_at' => now()->addMinutes(10),
        ]);

        Order::create([
            'business_id' => $business->id,
            'order_number' => self::MARKER . 'ORD-1001',
            'status' => 'paid',
            'total_cents' => 9900,
        ]);

        Order::create([
            'business_id' => $business->id,
            'order_number' => self::MARKER . 'ORD-1002',
            'status' => 'pending_payment',
            'total_cents' => 15000,
        ]);

        return 6;
    }

    public function purge(Business $business): int
    {
        $deletedOrders = Order::where('business_id', $business->id)
            ->where('order_number', 'like', self::MARKER . '%')
            ->delete();

        $deletedCarts = Cart::where('business_id', $business->id)
            ->where('session_token', 'demo_session_1')
            ->delete();

        $deletedSellables = Sellable::where('business_id', $business->id)
            ->where('name', 'like', self::MARKER . '%')
            ->delete();

        return $deletedOrders + $deletedCarts + $deletedSellables;
    }
}
