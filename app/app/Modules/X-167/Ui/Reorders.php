<?php

declare(strict_types=1);

namespace App\Modules\X167\Ui;

use App\Modules\X167\Models\PurchaseOrder;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Reorders extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $orders = ($this->businessId > 0)
            ? PurchaseOrder::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-167::reorders', [
            'orders' => $orders,
        ]);
    }
}
