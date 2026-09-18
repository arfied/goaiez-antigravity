<?php

declare(strict_types=1);

namespace App\Modules\X130\Ui;

use App\Modules\X130\Models\DemandRegion;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Demand by trade'])]
class CoverageByTrade extends Component
{
    public function render()
    {
        $trades = DemandRegion::select('trade_type')->distinct()->orderBy('trade_type')->get();

        return view('x-130::coverage-by-trade', [
            'trades' => $trades,
        ]);
    }
}
