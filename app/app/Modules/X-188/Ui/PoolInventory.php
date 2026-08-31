<?php

declare(strict_types=1);

namespace App\Modules\X188\Ui;

use App\Modules\X188\Models\NumberPool;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PoolInventory extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $numbers = ($this->businessId > 0)
            ? NumberPool::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-188::pool-inventory', [
            'numbers' => $numbers,
        ]);
    }
}
