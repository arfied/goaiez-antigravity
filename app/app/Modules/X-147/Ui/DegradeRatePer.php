<?php

declare(strict_types=1);

namespace App\Modules\X147\Ui;

use App\Modules\X147\Models\RcsCapability;
use Livewire\Component;

class DegradeRatePer extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $caps = ($this->businessId > 0)
            ? RcsCapability::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-147::degrade-rate-per', [
            'caps' => $caps,
        ]);
    }
}
