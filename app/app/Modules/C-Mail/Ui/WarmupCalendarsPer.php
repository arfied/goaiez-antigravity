<?php

declare(strict_types=1);

namespace App\Modules\CMail\Ui;

use App\Modules\CMail\Models\WarmupCalendar;
use Livewire\Attributes\Locked;
use Livewire\Component;

class WarmupCalendarsPer extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $calendars = ($this->businessId > 0)
            ? WarmupCalendar::where('business_id', $this->businessId)->get()
            : collect();

        return view('c-mail::warmup-calendars-per', [
            'calendars' => $calendars,
        ]);
    }
}
