<?php

declare(strict_types=1);

namespace App\Modules\X204\Ui;

use App\Modules\X204\Models\ComplianceRegister;
use Livewire\Component;

class RegisterSlotStates extends Component
{
    public int $businessId = 0;

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
