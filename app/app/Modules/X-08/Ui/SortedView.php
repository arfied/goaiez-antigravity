<?php

declare(strict_types=1);

namespace App\Modules\X08\Ui;

use App\Modules\X08\Models\ChurnScore;
use Livewire\Component;

class SortedView extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $sorted = ($this->businessId > 0)
            ? ChurnScore::where('business_id', $this->businessId)->orderByDesc('risk_score')->get()
            : collect();

        return view('x-08::sorted', [
            'sorted' => $sorted,
        ]);
    }
}
