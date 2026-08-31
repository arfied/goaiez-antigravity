<?php

declare(strict_types=1);

namespace App\Modules\X136\Ui;

use App\Modules\X136\Models\Signal;
use Livewire\Attributes\Locked;
use Livewire\Component;

class SignalVolumePrecisionView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $totalSignals = ($this->businessId > 0)
            ? Signal::where('business_id', $this->businessId)->count()
            : 0;

        return view('x-136::signal-volume-precision', [
            'totalSignals' => $totalSignals,
        ]);
    }
}
