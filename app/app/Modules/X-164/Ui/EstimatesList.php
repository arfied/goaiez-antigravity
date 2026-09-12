<?php

declare(strict_types=1);

namespace App\Modules\X164\Ui;

use App\Modules\X164\Models\Estimate;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Your estimates'])]
class EstimatesList extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $estimates = ($this->businessId > 0)
            ? Estimate::where('business_id', $this->businessId)->orderBy('id', 'desc')->get()
            : collect();

        return view('x-164::estimates-list', [
            'estimates' => $estimates,
        ]);
    }
}
