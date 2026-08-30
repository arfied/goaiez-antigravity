<?php

declare(strict_types=1);

namespace App\Modules\X145\Ui;

use App\Modules\X145\Models\Decision;
use Livewire\Component;

class ProposalsAppearToday extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $proposals = ($this->businessId > 0)
            ? Decision::where('business_id', $this->businessId)->where('status', 'proposed')->get()
            : collect();

        return view('x-145::proposals-appear-today', [
            'proposals' => $proposals,
        ]);
    }
}
