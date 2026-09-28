<?php

declare(strict_types=1);

namespace App\Modules\X10\Ui;

use App\Modules\X10\Models\Assignment;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Unassigned leads'])]
class UnassignedCount extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
        abort_if($this->businessId === 0, 404);
    }

    public function render()
    {
        $totalLeads = Assignment::where('business_id', $this->businessId)
            ->distinct('lead_id')
            ->count('lead_id');

        $activeLeads = Assignment::where('business_id', $this->businessId)
            ->where('status', 'active')
            ->distinct('lead_id')
            ->count('lead_id');

        $count = max(0, $totalLeads - $activeLeads);

        return view('x-10::unassigned-count', ['count' => $count]);
    }
}
