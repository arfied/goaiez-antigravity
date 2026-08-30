<?php

declare(strict_types=1);

namespace App\Modules\X111\Ui;

use App\Modules\X111\Models\OperatorAlert;
use App\Modules\X111\Models\TenantTicket;
use Livewire\Component;

class Console extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $alerts = ($this->businessId > 0)
            ? OperatorAlert::where('business_id', $this->businessId)->get()
            : collect();

        $tickets = ($this->businessId > 0)
            ? TenantTicket::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-111::console', [
            'alerts' => $alerts,
            'tickets' => $tickets,
        ]);
    }
}
