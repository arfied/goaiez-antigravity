<?php

declare(strict_types=1);

namespace App\Modules\X200\Ui;

use App\Modules\X200\Models\DialerSeat;
use Livewire\Component;

class AgentDesktop extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $seats = ($this->businessId > 0)
            ? DialerSeat::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-200::agent-desktop', [
            'seats' => $seats,
        ]);
    }
}
