<?php

declare(strict_types=1);

namespace App\Modules\X136\Ui;

use App\Modules\X136\Models\SignalScore;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CoolingView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $cooling = ($this->businessId > 0)
            ? SignalScore::where('business_id', $this->businessId)->where('cooling_status', 'cooling')->get()
            : collect();

        return view('x-136::cooling', [
            'cooling' => $cooling,
        ]);
    }
}
