<?php

declare(strict_types=1);

namespace App\Modules\X117\Ui;

use App\Modules\X117\Actions\CartAddAction;
use App\Modules\X117\Actions\CartRemoveAction;
use App\Modules\X117\Domain\SoldOutException;
use App\Modules\X117\Models\Cart;
use App\Modules\X117\Models\Sellable;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Cart'])]
class CartBlock extends Component
{
    #[Locked]
    public string $sessionToken = '';

    public ?string $error = null;

    public ?string $success = null;

    public ?string $waiting = null;

    public function mount(?string $sessionToken = null): void
    {
        $this->sessionToken = $sessionToken ?? session()->getId();
    }

    public function add(int $sellableId, CartAddAction $action): void
    {
        $this->error = null;
        $this->success = null;
        $this->waiting = null;
        try {
            $businessId = Tenancy::idOrFail();
            $sellable = Sellable::where('business_id', $businessId)->findOrFail($sellableId);
            $action->handle($businessId, $this->sessionToken, $sellableId);
            $this->success = sprintf('%s is in the cart.', $sellable->name);
        } catch (SoldOutException $e) {
            $this->error = $e->getMessage();
        } catch (ModelNotFoundException) {
            $this->error = "That item isn't in this catalogue any more.";
        } catch (\Throwable $e) {
            $this->error = 'We could not add that: '.$e->getMessage();
        }
    }

    public function remove(int $sellableId, CartRemoveAction $action): void
    {
        $this->error = null;
        $this->success = null;
        $this->waiting = null;
        try {
            $businessId = Tenancy::idOrFail();
            $sellable = Sellable::where('business_id', $businessId)->findOrFail($sellableId);
            $action->handle($businessId, $this->sessionToken, $sellableId);
            $this->success = sprintf('%s is out of the cart.', $sellable->name);
        } catch (ModelNotFoundException) {
            $this->error = "That item isn't in this cart any more.";
        } catch (\Throwable $e) {
            $this->error = 'We could not remove that: '.$e->getMessage();
        }
    }

    public function checkout(): void
    {
        $this->error = null;
        $this->success = null;
        $this->waiting = 'Checkout waits on the checkout block: every charge takes a fresh authorisation there. Stock comes off the moment the order is placed, before any payment, and comes back only if you cancel the order. Nothing was charged.';
    }

    public function render()
    {
        abort_unless(Tenancy::check(), 403);
        $businessId = Tenancy::idOrFail();

        $sellables = Sellable::where('business_id', $businessId)->orderBy('name')->orderBy('id')->get();
        $cart = Cart::where('business_id', $businessId)->where('session_token', $this->sessionToken)->first();
        $expired = $cart !== null && $cart->expires_at->isPast();

        $lines = [];
        if ($cart !== null && ! $expired) {
            foreach ($cart->items as $item) {
                $s = $sellables->firstWhere('id', (int) $item['sellable_id']);
                if ($s !== null) {
                    $qty = (int) ($item['quantity'] ?? 1);
                    $lines[] = ['sellable' => $s, 'quantity' => $qty, 'subtotal_cents' => $qty * $s->unit_price_cents];
                }
            }
        }

        return view('x-117::cart-block', [
            'sellables' => $sellables,
            'cart' => $cart,
            'expired' => $expired,
            'lines' => $lines,
        ]);
    }
}
