<?php

declare(strict_types=1);

namespace App\Modules\X188\Ui;

use App\Modules\X188\Models\NumberPool;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Number pool'])]
class PoolInventory extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $numbersByAreaCode = ($this->businessId > 0)
            ? NumberPool::where('business_id', $this->businessId)->get()->groupBy('area_code')
            : collect();

        return view('x-188::pool-inventory', [
            'numbersByAreaCode' => $numbersByAreaCode,
        ]);
    }
}
