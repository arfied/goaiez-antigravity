<?php

declare(strict_types=1);

namespace App\Modules\X163\Ui;

use App\Modules\X163\Models\PriceBookItem;
use Livewire\Component;

class Pricebook extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $items = ($this->businessId > 0)
            ? PriceBookItem::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-163::pricebook', [
            'items' => $items,
        ]);
    }
}
