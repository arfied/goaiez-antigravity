<?php

declare(strict_types=1);

namespace App\Modules\X170\Ui;

use App\Modules\X170\Models\Scorecard;
use Livewire\Component;

class ScorecardUi extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $scorecards = ($this->businessId > 0)
            ? Scorecard::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-170::scorecard', [
            'scorecards' => $scorecards,
        ]);
    }
}
