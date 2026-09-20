<?php

declare(strict_types=1);

namespace App\Modules\X149\Ui;

use App\Modules\X149\Models\QualitySeries;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class QualityBoard extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $series = ($this->businessId > 0)
            ? QualitySeries::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-149::quality-board', [
            'series' => $series,
        ]);
    }
}
