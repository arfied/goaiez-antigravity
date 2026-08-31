<?php

declare(strict_types=1);

namespace App\Modules\X82\Ui;

use App\Modules\X82\Models\Rate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RateRegistryView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $rates = ($this->businessId > 0)
            ? Rate::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-82::rate-registry', [
            'rates' => $rates,
        ]);
    }
}
