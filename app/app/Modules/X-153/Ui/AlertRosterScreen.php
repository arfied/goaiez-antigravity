<?php

declare(strict_types=1);

namespace App\Modules\X153\Ui;

use App\Modules\X153\Models\Alert;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AlertRosterScreen extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $alerts = ($this->businessId > 0)
            ? Alert::where('business_id', $this->businessId)->orderBy('id', 'desc')->get()
            : collect();

        return view('x-153::alert-roster-screen', [
            'alerts' => $alerts,
        ]);
    }
}
