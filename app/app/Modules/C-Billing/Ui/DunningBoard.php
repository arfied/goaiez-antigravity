<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Ui;

use App\Modules\CBilling\Models\DunningState;
use Livewire\Attributes\Locked;
use Livewire\Component;

class DunningBoard extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $states = ($this->businessId > 0)
            ? DunningState::where('business_id', $this->businessId)->get()
            : collect();

        return view('c-billing::dunning-board', [
            'states' => $states,
        ]);
    }
}
