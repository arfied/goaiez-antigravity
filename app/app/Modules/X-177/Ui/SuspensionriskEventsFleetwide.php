<?php

declare(strict_types=1);

namespace App\Modules\X177\Ui;

use App\Modules\X177\Models\GbpPost;
use Livewire\Attributes\Locked;
use Livewire\Component;

class SuspensionriskEventsFleetwide extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $riskEvents = ($this->businessId > 0)
            ? GbpPost::where('business_id', $this->businessId)->where('status', 'rejected_risk')->get()
            : collect();

        return view('x-177::suspensionrisk-events-fleetwide', [
            'riskEvents' => $riskEvents,
        ]);
    }
}
