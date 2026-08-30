<?php

declare(strict_types=1);

namespace App\Modules\X202\Ui;

use App\Modules\X202\Models\ApprovalItem;
use Livewire\Component;

class Queue extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $items = ($this->businessId > 0)
            ? ApprovalItem::where('business_id', $this->businessId)->where('status', 'pending')->get()
            : collect();

        return view('x-202::queue', [
            'items' => $items,
        ]);
    }
}
