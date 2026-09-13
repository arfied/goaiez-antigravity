<?php

declare(strict_types=1);

namespace App\Modules\X184\Ui;

use App\Modules\X184\Models\PlanItem;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Your content calendar'])]
class CalendarView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $items = ($this->businessId > 0)
            ? PlanItem::where('business_id', $this->businessId)->orderBy('scheduled_date')->orderBy('channel')->get()
            : collect();

        return view('x-184::calendar', [
            'items' => $items,
        ]);
    }
}
