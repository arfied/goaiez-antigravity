<?php

declare(strict_types=1);

namespace App\Modules\X16\Ui;

use App\Modules\X16\Models\ServicePolygon;
use Livewire\Component;

class ServiceareaPolygon extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $polygons = ($this->businessId > 0)
            ? ServicePolygon::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-16::servicearea-polygon', [
            'polygons' => $polygons,
        ]);
    }
}
