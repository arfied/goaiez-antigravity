<?php

declare(strict_types=1);

namespace App\Modules\X188\Ui;

use App\Modules\X188\Models\NumberPark;
use Livewire\Component;

class ParkList extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $parks = ($this->businessId > 0)
            ? NumberPark::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-188::park-list', [
            'parks' => $parks,
        ]);
    }
}
