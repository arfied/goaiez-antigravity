<?php

declare(strict_types=1);

namespace App\Modules\X16\Ui;

use App\Modules\X16\Models\GeoGrid;
use Livewire\Attributes\Locked;
use Livewire\Component;

class GeogridMap extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $grids = ($this->businessId > 0)
            ? GeoGrid::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-16::geogrid-map', [
            'grids' => $grids,
        ]);
    }
}
