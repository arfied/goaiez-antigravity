<?php

declare(strict_types=1);

namespace App\Modules\X206\Ui;

use App\Modules\X206\Models\Credential;
use Livewire\Component;

class Connections extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $creds = ($this->businessId > 0)
            ? Credential::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-206::connections', [
            'credentials' => $creds,
        ]);
    }
}
