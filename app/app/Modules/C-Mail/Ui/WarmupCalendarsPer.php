<?php

declare(strict_types=1);

namespace App\Modules\CMail\Ui;

use App\Modules\CMail\Models\WarmupCalendar;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Domain warm-up'])]
class WarmupCalendarsPer extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

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
