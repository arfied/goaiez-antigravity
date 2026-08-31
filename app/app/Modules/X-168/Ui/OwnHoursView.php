<?php

declare(strict_types=1);

namespace App\Modules\X168\Ui;

use App\Modules\X168\Models\Timesheet;
use Livewire\Attributes\Locked;
use Livewire\Component;

class OwnHoursView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public int $personId = 0;

    public function render()
    {
        $timesheets = ($this->businessId > 0 && $this->personId > 0)
            ? Timesheet::where('business_id', $this->businessId)->where('person_id', $this->personId)->get()
            : collect();

        return view('x-168::own-hours', [
            'timesheets' => $timesheets,
        ]);
    }
}
