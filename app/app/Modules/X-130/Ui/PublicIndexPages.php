<?php

declare(strict_types=1);

namespace App\Modules\X130\Ui;

use App\Modules\X130\Models\DemandRegion;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Demand in your area'])]
class PublicIndexPages extends Component
{
    public function render()
    {
        $regions = DemandRegion::with('series')->orderBy('region_name')->get();

        return view('x-130::public-index-pages', [
            'regions' => $regions,
        ]);
    }
}
