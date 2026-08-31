<?php

declare(strict_types=1);

namespace App\Modules\X10\Ui;

use App\Modules\X10\Models\Territory;
use Livewire\Attributes\Locked;
use Livewire\Component;

class TerritoryMap extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $territories = ($this->businessId > 0)
            ? Territory::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-10::territory-map', [
            'territories' => $territories,
        ]);
    }
}
