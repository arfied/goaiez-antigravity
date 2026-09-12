<?php

declare(strict_types=1);

namespace App\Modules\X10\Ui;

use App\Modules\X10\Models\Territory;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Your service areas'])]
class TerritoryMap extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $territories = ($this->businessId > 0)
            ? Territory::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-10::territory-map', [
            'territories' => $territories,
        ]);
    }
}
