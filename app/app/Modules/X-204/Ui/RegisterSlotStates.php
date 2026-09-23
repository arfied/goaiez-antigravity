<?php

declare(strict_types=1);

namespace App\Modules\X204\Ui;

use App\Modules\X204\Actions\GetRegistersAction;
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

    public function render(GetRegistersAction $action)
    {
        $registers = ($this->businessId > 0)
            ? $action->handle($this->businessId)
            : collect();

        return view('x-204::register-slot-states', [
            'registers' => $registers,
        ]);
    }
}
