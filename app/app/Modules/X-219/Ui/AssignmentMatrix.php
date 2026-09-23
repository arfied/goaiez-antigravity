<?php

declare(strict_types=1);

namespace App\Modules\X219\Ui;

use App\Modules\X219\Models\AiModuleAssignment;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AssignmentMatrix extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

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
