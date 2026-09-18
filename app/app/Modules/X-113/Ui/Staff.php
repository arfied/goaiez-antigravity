<?php

declare(strict_types=1);

namespace App\Modules\X113\Ui;

use App\Modules\X113\Models\StaffUser;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Staff'])]
class Staff extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(): void
    {
        $this->businessId = Tenancy::id() ?? 0;
    }

    public function render()
    {
        $staff = ($this->businessId > 0)
            ? StaffUser::where('business_id', $this->businessId)->orderBy('name')->get()
            : collect();

        return view('x-113::staff', [
            'staff' => $staff,
        ]);
    }
}
