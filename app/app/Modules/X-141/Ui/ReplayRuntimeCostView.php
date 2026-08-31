<?php

declare(strict_types=1);

namespace App\Modules\X141\Ui;

use App\Modules\X141\Models\ReplayRun;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ReplayRuntimeCostView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $runs = ($this->businessId > 0)
            ? ReplayRun::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-141::replay-runtime-cost', [
            'runs' => $runs,
        ]);
    }
}
