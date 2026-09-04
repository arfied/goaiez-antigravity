<?php

declare(strict_types=1);

namespace App\Modules\X112\Ui;

use App\Modules\X112\Models\StaffRole;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.agency')]
class Staff extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $staff = ($this->businessId > 0)
            ? StaffRole::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-112::staff', [
            'staff' => $staff,
        ]);
    }
}
