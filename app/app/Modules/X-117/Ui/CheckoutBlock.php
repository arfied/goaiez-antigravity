<?php

declare(strict_types=1);

namespace App\Modules\X117\Ui;

use App\Modules\X117\Actions\CartPayAction;
use App\Modules\X117\Actions\OrderCancelAction;
use App\Modules\X117\Models\Cart;
use App\Modules\X117\Models\Order;
use App\Modules\X117\Models\Sellable;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CheckoutBlock extends Component
{
    #[Locked]
    public string $sessionToken = '';

    public ?string $authToken = null;

    public ?string $error = null;

    public ?string $success = null;

    public ?string $authorised = null;

    public ?string $waiting = null;

    public function mount(?string $sessionToken = null): void
    {
        $this->sessionToken = $sessionToken ?? session()->getId();
    }

    public function authorise(): void
    {
        $this->error = null;
        $this->success = null;
        $this->authorised = null;
        $this->waiting = null;

        $this->authToken = 'auth_'.Str::random(20);
        $this->authorised = sprintf('Authorised at %s — the order will be placed and is waiting on a card-entry surface that is not connected yet.', now()->format('H:i:s'));
    }

    public function pay(CartPayAction $action): void
    {
        $this->error = null;
        $this->success = null;
        $this->authorised = null;
        $this->waiting = null;

        if ($this->authToken === null) {
            $this->error = 'This charge needs a fresh authorisation: tap Authorise first. Nothing was charged.';

            return;
        }

        try {
            $r = $action->handle(Tenancy::idOrFail(), $this->sessionToken, $this->authToken);
            if ($r['status'] === 'paid') {
                $this->success = sprintf('Paid — order %s. The charge landed at the gateway.', $r['order_number']);
            } elseif ($r['status'] === 'pending_payment') {
                $this->waiting = sprintf('Pending — order %s. The order was placed, stock came off, and it is waiting on a card-entry surface.', $r['order_number']);
            } else {
                $this->error = $r['message'] ?? 'Payment failed.';
            }
        } catch (\Throwable $e) {
            $this->error = 'We could not take that payment: '.$e->getMessage();
        } finally {
            $this->authToken = null;
        }
    }

    public function cancel(int $orderId, OrderCancelAction $action): void
    {
        $this->error = null;
        $this->success = null;
        $this->authorised = null;
        $this->waiting = null;

        try {
            $action->handle(Tenancy::idOrFail(), $orderId);
            $this->success = sprintf('Order %d cancelled; its stock is back on the shelf (§147.2).', $orderId);
        } catch (ModelNotFoundException $e) {
            $this->error = "That order isn't in this account.";
        } catch (\Throwable $e) {
            $this->error = 'We could not cancel that: '.$e->getMessage();
        }
    }

    public function render()
    {
        abort_unless(Tenancy::check(), 403);
        $businessId = Tenancy::idOrFail();

        $cart = Cart::where('business_id', $businessId)->where('session_token', $this->sessionToken)->first();
        $expired = $cart !== null && $cart->expires_at->isPast();

        $lines = [];
        if ($cart !== null && ! $expired) {
            foreach ($cart->items as $item) {
                $s = Sellable::find((int) $item['sellable_id']);
                if ($s !== null) {
                    $qty = (int) ($item['quantity'] ?? 1);
                    $lines[] = ['sellable' => $s, 'quantity' => $qty, 'subtotal_cents' => $qty * $s->unit_price_cents];
                }
            }
        }

        $orders = Order::where('business_id', $businessId)->orderByDesc('id')->limit(10)->get();

        return view('x-117::checkout-block', [
            'cart' => $cart,
            'expired' => $expired,
            'lines' => $lines,
            'orders' => $orders,
        ]);
    }
}
