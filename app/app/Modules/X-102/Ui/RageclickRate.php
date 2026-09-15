<?php

declare(strict_types=1);

namespace App\Modules\X102\Ui;

use App\Modules\X102\Models\ChatSession;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Rage clicks'])]
class RageclickRate extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        return view('x-102::rageclick-rate', [
            'sessions' => ($this->businessId > 0) ? ChatSession::where('business_id', $this->businessId)->where('rage_clicks_count', '>', 0)->orderByDesc('rage_clicks_count')->get() : collect(),
        ]);
    }
}
