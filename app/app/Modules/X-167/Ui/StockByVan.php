<?php

declare(strict_types=1);

namespace App\Modules\X167\Ui;

use App\Modules\X167\Models\StockItem;
use Livewire\Attributes\Locked;
use Livewire\Component;

class StockByVan extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $items = ($this->businessId > 0)
            ? StockItem::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-167::stock-by-van', [
            'items' => $items,
        ]);
    }
}
