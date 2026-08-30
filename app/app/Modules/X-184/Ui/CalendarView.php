<?php

declare(strict_types=1);

namespace App\Modules\X184\Ui;

use App\Modules\X184\Models\PlanItem;
use Livewire\Component;

class CalendarView extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $items = ($this->businessId > 0)
            ? PlanItem::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-184::calendar', [
            'items' => $items,
        ]);
    }
}
