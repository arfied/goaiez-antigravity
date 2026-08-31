<?php

declare(strict_types=1);

namespace App\Modules\X173\Ui;

use App\Modules\X173\Models\AccountingSyncConflict;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ConflictsListView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $conflicts = ($this->businessId > 0)
            ? AccountingSyncConflict::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-173::conflicts-list', [
            'conflicts' => $conflicts,
        ]);
    }
}
