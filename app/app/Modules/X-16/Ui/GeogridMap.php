<?php

declare(strict_types=1);

namespace App\Modules\X16\Ui;

use App\Modules\X16\Models\GeoGrid;
use Livewire\Component;

class GeogridMap extends Component
{
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
