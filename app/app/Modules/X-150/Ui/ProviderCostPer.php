<?php

declare(strict_types=1);

namespace App\Modules\X150\Ui;

use App\Modules\X150\Models\ProviderRoster;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ProviderCostPer extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $providers = ($this->businessId > 0)
            ? ProviderRoster::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-150::provider-cost-per', [
            'providers' => $providers,
        ]);
    }
}
