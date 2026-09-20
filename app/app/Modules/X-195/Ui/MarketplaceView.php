<?php

declare(strict_types=1);

namespace App\Modules\X195\Ui;

use App\Modules\X195\Models\MarketItem;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class MarketplaceView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $items = ($this->businessId > 0)
            ? MarketItem::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-195::marketplace', [
            'items' => $items,
        ]);
    }
}
