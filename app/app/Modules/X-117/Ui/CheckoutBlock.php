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
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Checkout'])]
class CheckoutBlock extends Component
{
    use LabelsOrderStatus;

    private const ORDER_WINDOW = 10;

    #[Locked]
    public string $sessionToken = '';

    public ?string $authToken = null;

    public ?string $error = null;

    public ?string $errorHeading = null;

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
        $this->errorHeading = null;
        $this->success = null;
        $this->authorised = null;
        $this->waiting = null;

        $this->authToken = 'auth_'.Str::random(20);
        $this->authorised = sprintf('A one-time checkout token was issued at %s and can be used once. Nothing was authorised at any gateway: this waits on a card-entry surface that is not connected yet.', now()->format('H:i:s'));
    }

    public function pay(CartPayAction $action): void
    {
        $this->error = null;
        $this->errorHeading = 'Could not take that payment';
        $this->success = null;
        $this->authorised = null;
        $this->waiting = null;

        if ($this->authToken === null) {
            $this->error = 'This order needs a fresh authorisation: tap Authorise first. Nothing was placed, and nothing is charged here in any case: this checkout is waiting on a card-entry surface.';

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
        $this->errorHeading = 'Could not cancel that order';
        $this->success = null;
        $this->authorised = null;
        $this->waiting = null;

        try {
            $action->handle(Tenancy::idOrFail(), $orderId);
            $this->success = sprintf('Order %d cancelled; its stock is back on the shelf.', $orderId);
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
        $unlistedCount = 0;
        if ($cart !== null && ! $expired) {
            foreach ($cart->items as $item) {
                $s = Sellable::where('business_id', $businessId)->find((int) $item['sellable_id']);
                if ($s !== null) {
                    $qty = (int) ($item['quantity'] ?? 1);
                    $lines[] = ['sellable' => $s, 'quantity' => $qty, 'subtotal_cents' => $qty * $s->unit_price_cents];
                } else {
                    $unlistedCount++;
                }
            }
        }

        $orders = Order::where('business_id', $businessId)->orderByDesc('id')->limit(self::ORDER_WINDOW + 1)->get();

        // One row past the window is the overflow probe: no second query, no count().
        $ordersTruncated = $orders->count() > self::ORDER_WINDOW;
        $orders = $orders->take(self::ORDER_WINDOW)->values();

        return view('x-117::checkout-block', [
            'cart' => $cart,
            'expired' => $expired,
            'lines' => $lines,
            'unlistedCount' => $unlistedCount,
            'orders' => $orders,
            'ordersTruncated' => $ordersTruncated,
            'orderStatusLabels' => $this->orderStatusLabels(),
            'orderStatusPillStates' => $this->orderStatusPillStates(),
        ]);
    }
}
