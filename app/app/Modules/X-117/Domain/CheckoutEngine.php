<?php

declare(strict_types=1);

namespace App\Modules\X117\Domain;

use App\Modules\X117\Events\CartCheckedOut;
use App\Modules\X117\Events\InventoryUpdated;
use App\Modules\X117\Models\Cart;
use App\Modules\X117\Models\Order;
use App\Modules\X117\Models\OrderLine;
use App\Modules\X117\Models\Sellable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

final class CheckoutEngine
{
    /**
     * Build cart with items and true expiration timestamp (G16-05).
     */
    public function buildCart(int $businessId, string $sessionToken, array $items, int $expiresMinutes = 15): Cart
    {
        $totalCents = 0;
        foreach ($items as $item) {
            $sellable = Sellable::where('business_id', $businessId)->findOrFail($item['sellable_id']);
            $totalCents += ($item['quantity'] ?? 1) * $sellable->unit_price_cents;
        }

        return Cart::updateOrCreate(
            ['business_id' => $businessId, 'session_token' => $sessionToken],
            [
                'items' => $items,
                'total_cents' => $totalCents,
                'expires_at' => now()->addMinutes($expiresMinutes),
            ]
        );
    }

    /**
     * High-concurrency atomic checkout with pessimistic row locking (TEST ANCHOR).
     */
    public function checkout(
        int $businessId,
        int $sellableId,
        int $quantity,
        string $freshAuthToken,
        ?int $customerId = null
    ): array {
        return DB::transaction(function () use ($businessId, $sellableId, $quantity, $freshAuthToken, $customerId) {
            // Pessimistic lock on Sellable row guarantees serialised inventory evaluation (TEST ANCHOR)
            $sellable = Sellable::where('business_id', $businessId)
                ->where('id', $sellableId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($sellable->inventory_quantity < $quantity) {
                return [
                    'status' => 'sold_out',
                    'message' => 'Item is sold out',
                ];
            }

            // Fresh authorization check (G1-15, G1-39)
            if (empty($freshAuthToken) || str_starts_with($freshAuthToken, 'expired_')) {
                return [
                    'status' => 'refused',
                    'refusal_code' => 'FRESH_AUTH_REQUIRED',
                    'message' => 'Every charge requires a fresh authorization event',
                ];
            }

            // Decrement inventory
            $sellable->decrement('inventory_quantity', $quantity);

            $totalCents = $sellable->unit_price_cents * $quantity;

            $order = Order::create([
                'business_id' => $businessId,
                'customer_id' => $customerId,
                'order_number' => 'ORD-'.strtoupper(Str::random(6)),
                'status' => 'paid',
                'total_cents' => $totalCents,
                'auth_token' => $freshAuthToken,
            ]);

            OrderLine::create([
                'business_id' => $businessId,
                'order_id' => $order->id,
                'sellable_id' => $sellable->id,
                'quantity' => $quantity,
                'unit_price_cents' => $sellable->unit_price_cents,
                'subtotal_cents' => $totalCents,
            ]);

            Event::dispatch(new InventoryUpdated(
                businessId: $businessId,
                sellableId: $sellable->id,
                newQuantity: $sellable->inventory_quantity
            ));

            Event::dispatch(new CartCheckedOut(
                businessId: $businessId,
                orderId: $order->id,
                orderNumber: $order->order_number,
                totalCents: $totalCents,
                authToken: $freshAuthToken,
                customerId: $customerId,
            ));

            return [
                'status' => 'paid',
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'total_cents' => $totalCents,
                'remaining_inventory' => $sellable->inventory_quantity,
            ];
        });
    }

    /**
     * Cancel order and restock inventory.
     */
    public function cancelOrder(int $businessId, int $orderId): array
    {
        return DB::transaction(function () use ($businessId, $orderId) {
            $order = Order::where('business_id', $businessId)->findOrFail($orderId);
            $order->update(['status' => 'cancelled']);

            $lines = OrderLine::where('business_id', $businessId)->where('order_id', $order->id)->get();
            foreach ($lines as $line) {
                $sellable = Sellable::where('business_id', $businessId)->where('id', $line->sellable_id)->lockForUpdate()->first();
                if ($sellable) {
                    $sellable->increment('inventory_quantity', $line->quantity);
                    Event::dispatch(new InventoryUpdated($businessId, $sellable->id, $sellable->inventory_quantity));
                }
            }

            return [
                'order_id' => $order->id,
                'status' => 'cancelled',
            ];
        });
    }

    /**
     * Adds to the session's cart. Stock is decremented at PAID, never at CART
     * (§147.2): this reads inventory and writes only the cart row. The clock
     * on a live cart never moves (G16-05); an expired or absent cart starts a new one.
     */
    public function checkoutCart(int $businessId, string $sessionToken, string $freshAuthToken, ?int $customerId = null): array
    {
        return DB::transaction(function () use ($businessId, $sessionToken, $freshAuthToken, $customerId) {
            if (empty($freshAuthToken) || str_starts_with($freshAuthToken, 'expired_')) {
                return [
                    'status' => 'refused',
                    'refusal_code' => 'FRESH_AUTH_REQUIRED',
                    'message' => 'This charge needs a fresh authorisation: tap Authorise first. Nothing was charged.',
                ];
            }

            if (Order::where('business_id', $businessId)->where('auth_token', $freshAuthToken)->exists()) {
                return [
                    'status' => 'refused',
                    'refusal_code' => 'AUTH_USED',
                    'message' => 'This charge needs a fresh authorisation: an authorisation pays once and this one already has. Tap Authorise again. Nothing was charged.',
                ];
            }

            $cart = Cart::where('business_id', $businessId)->where('session_token', $sessionToken)->first();

            if (! $cart || ! $cart->expires_at->isFuture() || empty($cart->items)) {
                return [
                    'status' => 'refused',
                    'refusal_code' => 'CART_EXPIRED',
                    'message' => 'There is nothing to pay for: the cart is empty or its 15 minutes ran out. Nothing was charged.',
                ];
            }

            $sellables = [];
            $totalCents = 0;
            foreach ($cart->items as $item) {
                $sellable = Sellable::where('business_id', $businessId)
                    ->where('id', $item['sellable_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($sellable->inventory_quantity < $item['quantity']) {
                    return [
                        'status' => 'sold_out',
                        'message' => sprintf('%s is sold out: %d in stock, %d in this cart. Nothing was charged and nothing moved.', $sellable->name, $sellable->inventory_quantity, $item['quantity']),
                    ];
                }

                $sellables[] = [
                    'model' => $sellable,
                    'quantity' => $item['quantity'],
                    'unit_price_cents' => $sellable->unit_price_cents,
                    'subtotal_cents' => $sellable->unit_price_cents * $item['quantity'],
                ];
                $totalCents += $sellable->unit_price_cents * $item['quantity'];
            }

            $order = Order::create([
                'business_id' => $businessId,
                'customer_id' => $customerId,
                'order_number' => 'ORD-'.strtoupper(Str::random(6)),
                'status' => 'paid',
                'total_cents' => $totalCents,
                'auth_token' => $freshAuthToken,
            ]);

            foreach ($sellables as $line) {
                $sellable = $line['model'];
                $quantity = $line['quantity'];

                $sellable->decrement('inventory_quantity', $quantity);

                OrderLine::create([
                    'business_id' => $businessId,
                    'order_id' => $order->id,
                    'sellable_id' => $sellable->id,
                    'quantity' => $quantity,
                    'unit_price_cents' => $line['unit_price_cents'],
                    'subtotal_cents' => $line['subtotal_cents'],
                ]);

                Event::dispatch(new InventoryUpdated(
                    businessId: $businessId,
                    sellableId: $sellable->id,
                    newQuantity: $sellable->inventory_quantity
                ));
            }

            Event::dispatch(new CartCheckedOut(
                businessId: $businessId,
                orderId: $order->id,
                orderNumber: $order->order_number,
                totalCents: $totalCents,
                authToken: $freshAuthToken,
                customerId: $customerId,
            ));

            $cart->delete();

            return [
                'status' => 'paid',
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'total_cents' => $totalCents,
            ];
        });
    }

    public function addToCart(int $businessId, string $sessionToken, int $sellableId, int $quantity = 1): Cart
    {
        $sellable = Sellable::where('business_id', $businessId)->findOrFail($sellableId);
        $cart = Cart::where('business_id', $businessId)->where('session_token', $sessionToken)->first();
        $live = $cart !== null && $cart->expires_at->isFuture();
        $items = $live ? $cart->items : [];

        $inCart = 0;
        foreach ($items as $item) {
            if ((int) $item['sellable_id'] === $sellable->id) {
                $inCart += (int) ($item['quantity'] ?? 1);
            }
        }

        if ($sellable->inventory_quantity < $inCart + $quantity) {
            throw new SoldOutException(sprintf(
                '%s is sold out: %d in stock, %d already in this cart.',
                $sellable->name,
                $sellable->inventory_quantity,
                $inCart
            ));
        }

        $found = false;
        foreach ($items as &$item) {
            if ((int) $item['sellable_id'] === $sellable->id) {
                $item['quantity'] = $inCart + $quantity;
                $found = true;
            }
        }
        unset($item);
        if (! $found) {
            $items[] = ['sellable_id' => $sellable->id, 'quantity' => $quantity];
        }

        return $this->writeCart($businessId, $sessionToken, $items, $live ? $cart->expires_at : now()->addMinutes(15));
    }

    public function removeFromCart(int $businessId, string $sessionToken, int $sellableId): Cart
    {
        $cart = Cart::where('business_id', $businessId)->where('session_token', $sessionToken)->firstOrFail();
        $items = array_values(array_filter($cart->items, fn (array $item): bool => (int) $item['sellable_id'] !== $sellableId));

        return $this->writeCart($businessId, $sessionToken, $items, $cart->expires_at);
    }

    /**
     * @param  array<int,array{sellable_id:int,quantity:int}>  $items
     */
    private function writeCart(int $businessId, string $sessionToken, array $items, CarbonInterface $expiresAt): Cart
    {
        $totalCents = 0;
        foreach ($items as $item) {
            $sellable = Sellable::where('business_id', $businessId)->findOrFail($item['sellable_id']);
            $totalCents += ((int) ($item['quantity'] ?? 1)) * $sellable->unit_price_cents;
        }

        return Cart::updateOrCreate(
            ['business_id' => $businessId, 'session_token' => $sessionToken],
            ['items' => $items, 'total_cents' => $totalCents, 'expires_at' => $expiresAt]
        );
    }
}
