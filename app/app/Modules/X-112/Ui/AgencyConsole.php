<?php

declare(strict_types=1);

namespace App\Modules\X112\Ui;

use App\Modules\X112\Models\AgencyClient;
use Livewire\Component;

class AgencyConsole extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $clients = ($this->businessId > 0)
            ? AgencyClient::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-112::agency-console', [
            'clients' => $clients,
        ]);
    }
}
