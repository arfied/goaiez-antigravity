<?php

declare(strict_types=1);

namespace App\Modules\X130\Ui;

use App\Modules\X130\Models\DemandSeries;
use Livewire\Component;

class DemandTile extends Component
{
    public function render()
    {
        $latest = DemandSeries::where('is_published', true)->latest('period_date')->first();

        return view('x-130::demand-tile', [
            'latest' => $latest,
        ]);
    }
}
