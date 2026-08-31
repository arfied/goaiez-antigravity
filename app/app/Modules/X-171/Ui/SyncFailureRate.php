<?php

declare(strict_types=1);

namespace App\Modules\X171\Ui;

use App\Modules\X171\Models\DeviceSyncConflict;
use Livewire\Attributes\Locked;
use Livewire\Component;

class SyncFailureRate extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $conflicts = ($this->businessId > 0)
            ? DeviceSyncConflict::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-171::sync-failure-rate', [
            'conflicts' => $conflicts,
        ]);
    }
}
