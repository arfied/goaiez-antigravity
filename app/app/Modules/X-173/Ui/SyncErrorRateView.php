<?php

declare(strict_types=1);

namespace App\Modules\X173\Ui;

use App\Modules\X173\Models\SyncRun;
use Livewire\Component;

class SyncErrorRateView extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $runs = ($this->businessId > 0)
            ? SyncRun::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-173::sync-error-rate', [
            'runs' => $runs,
        ]);
    }
}
