<?php

declare(strict_types=1);

namespace App\Modules\X117\Ui;

use App\Modules\X117\Actions\CartPayAction;
use App\Support\Tenancy;
use Illuminate\Support\Str;
use Livewire\Component;

class CheckoutBlock extends Component
{
    public string $sessionToken;
    public string $freshAuthToken = '';
    public string $message = '';

    public function mount(string $sessionToken)
    {
        $this->sessionToken = $sessionToken;
    }

    public function authorise()
    {
        $this->freshAuthToken = 'auth_' . Str::random(12);
        $this->message = 'Authorised.';
    }

    public function pay(CartPayAction $action)
    {
        try {
            $result = $action->handle(Tenancy::idOrFail(), $this->sessionToken, $this->freshAuthToken);
            if ($result['status'] === 'refused' || $result['status'] === 'sold_out') {
                $this->message = $result['message'];
            } else {
                $this->message = 'Paid ' . number_format($result['total_cents'] / 100, 2);
            }
        } catch (\Exception $e) {
            $this->message = $e->getMessage();
        }
    }

    public function cancel()
    {
        $this->freshAuthToken = '';
        $this->message = 'Cancelled.';
    }

    public function render()
    {
        return view('x-117::checkout-block');
    }
}
