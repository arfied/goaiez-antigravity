<?php

declare(strict_types=1);

namespace App\Modules\X166\Ui;

use App\Modules\X166\Models\JobCost;
use Livewire\Component;

class MarginByJob extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $costs = ($this->businessId > 0)
            ? JobCost::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-166::margin-by-job', [
            'costs' => $costs,
        ]);
    }
}
