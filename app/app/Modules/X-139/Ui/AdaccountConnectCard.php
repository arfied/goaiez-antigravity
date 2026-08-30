<?php

declare(strict_types=1);

namespace App\Modules\X139\Ui;

use App\Modules\X139\Models\AdConnection;
use Livewire\Component;

class AdaccountConnectCard extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $connections = ($this->businessId > 0)
            ? AdConnection::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-139::adaccount-connect-card', [
            'connections' => $connections,
        ]);
    }
}
