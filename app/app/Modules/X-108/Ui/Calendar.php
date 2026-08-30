<?php

declare(strict_types=1);

namespace App\Modules\X108\Ui;

use App\Modules\X108\Models\Appointment;
use Livewire\Component;

class Calendar extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $appointments = ($this->businessId > 0)
            ? Appointment::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-108::calendar', [
            'appointments' => $appointments,
        ]);
    }
}
