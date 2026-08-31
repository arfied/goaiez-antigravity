<?php

declare(strict_types=1);

namespace App\Modules\X16\Ui;

use App\Modules\X16\Models\PlacesRecord;
use Livewire\Attributes\Locked;
use Livewire\Component;

class HarvestCoverageBy extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $records = ($this->businessId > 0)
            ? PlacesRecord::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-16::harvest-coverage-by', [
            'records' => $records,
        ]);
    }
}
