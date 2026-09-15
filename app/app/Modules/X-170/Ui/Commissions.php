<?php

declare(strict_types=1);

namespace App\Modules\X170\Ui;

use App\Modules\X170\Models\Commission;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Commissions'])]
class Commissions extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $commissions = ($this->businessId > 0)
            ? Commission::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-170::commissions', [
            'commissions' => $commissions,
        ]);
    }
}
