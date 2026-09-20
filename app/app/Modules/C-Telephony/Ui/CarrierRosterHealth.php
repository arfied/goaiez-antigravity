<?php

declare(strict_types=1);

namespace App\Modules\CTelephony\Ui;

use App\Modules\CTelephony\Models\CarrierHealth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use App\Support\Tenancy;

class CarrierRosterHealth extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $health = ($this->businessId > 0)
            ? CarrierHealth::where('business_id', $this->businessId)->get()
            : collect();

        return view('c-telephony::carrier-roster-health', [
            'health' => $health,
        ]);
    }
}
