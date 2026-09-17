<?php

declare(strict_types=1);

namespace App\Modules\X209\Ui;

use App\Modules\X209\Models\FixerLadder;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Autopilot ladder'])]
class LaddersOwnState extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(): void
    {
        $this->businessId = Tenancy::id() ?? 0;
    }

    public function render()
    {
        $ladders = ($this->businessId > 0)
            ? FixerLadder::where('business_id', $this->businessId)->orderBy('action_name')->get()
            : collect();

        return view('x-209::ladders-own-state', [
            'ladders' => $ladders,
        ]);
    }
}
