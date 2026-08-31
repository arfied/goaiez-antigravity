<?php

declare(strict_types=1);

namespace App\Modules\X08\Ui;

use App\Modules\X08\Models\ChurnScore;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RiskListView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $scores = ($this->businessId > 0)
            ? ChurnScore::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-08::risk-list', [
            'scores' => $scores,
        ]);
    }
}
