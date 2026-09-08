<?php

declare(strict_types=1);

namespace App\Modules\X188\Ui;

use App\Modules\X188\Models\NumberPark;
use Livewire\Component;

class ParkList extends Component
{
    public function render()
    {
        $parks = NumberPark::where('is_released', false)->get();

        return view('x-188::park-list', [
            'parks' => $parks,
        ]);
    }
}
