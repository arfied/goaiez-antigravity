<?php

declare(strict_types=1);

namespace App\Modules\X204\Ui;

use App\Modules\X204\Models\ComplianceRegister;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Compliance Register Slot States'])]
class RegisterSlotStates extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount()
    {
        $this->businessId = Tenancy::id() ?? 0;
    }

    public function render()
    {
        $registers = ($this->businessId > 0)
            ? ComplianceRegister::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-204::register-slot-states', [
            'registers' => $registers,
        ]);
    }
}
