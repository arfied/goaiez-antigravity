<?php

declare(strict_types=1);

namespace App\Modules\X177\Ui;

use App\Modules\X177\Models\GbpConnection;
use Livewire\Attributes\Locked;
use Livewire\Component;

class GbpCard extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $connections = ($this->businessId > 0)
            ? GbpConnection::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-177::gbp-card', [
            'connections' => $connections,
        ]);
    }
}
