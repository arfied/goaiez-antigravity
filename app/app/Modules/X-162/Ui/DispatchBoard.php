<?php

declare(strict_types=1);

namespace App\Modules\X162\Ui;

use App\Modules\X162\Models\DispatchAssignment;
use Livewire\Component;

class DispatchBoard extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $assignments = ($this->businessId > 0)
            ? DispatchAssignment::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-162::dispatch-board', [
            'assignments' => $assignments,
        ]);
    }
}
