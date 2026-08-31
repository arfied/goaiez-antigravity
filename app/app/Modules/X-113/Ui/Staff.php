<?php

declare(strict_types=1);

namespace App\Modules\X113\Ui;

use App\Modules\X113\Models\StaffUser;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Staff extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $staff = ($this->businessId > 0)
            ? StaffUser::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-113::staff', [
            'staff' => $staff,
        ]);
    }
}
