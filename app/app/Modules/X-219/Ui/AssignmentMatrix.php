<?php

declare(strict_types=1);

namespace App\Modules\X219\Ui;

use App\Modules\X219\Models\AiModuleAssignment;
use Livewire\Component;

class AssignmentMatrix extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $assignments = ($this->businessId > 0)
            ? AiModuleAssignment::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-219::assignment-matrix', [
            'assignments' => $assignments,
        ]);
    }
}
