<?php

declare(strict_types=1);

namespace App\Modules\X139\Ui;

use App\Modules\X139\Models\AdConnection;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Ad Platform Connections'])]
class AdaccountConnectCard extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

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
