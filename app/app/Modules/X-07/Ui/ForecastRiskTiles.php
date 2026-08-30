<?php

declare(strict_types=1);

namespace App\Modules\X07\Ui;

use App\Modules\X07\Models\Forecast;
use Livewire\Component;

class ForecastRiskTiles extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $forecasts = ($this->businessId > 0)
            ? Forecast::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-07::forecast-risk-tiles', [
            'forecasts' => $forecasts,
        ]);
    }
}
