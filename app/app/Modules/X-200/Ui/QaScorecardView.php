<?php

declare(strict_types=1);

namespace App\Modules\X200\Ui;

use App\Modules\X200\Models\QaScorecard;
use Livewire\Attributes\Locked;
use Livewire\Component;

class QaScorecardView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $scorecards = ($this->businessId > 0)
            ? QaScorecard::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-200::qa-scorecard', [
            'scorecards' => $scorecards,
        ]);
    }
}
