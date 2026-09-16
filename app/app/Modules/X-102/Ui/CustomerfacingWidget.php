<?php

declare(strict_types=1);

namespace App\Modules\X102\Ui;

use App\Modules\X102\Models\ChatSession;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Chat widget'])]
class CustomerfacingWidget extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $sessions = ($this->businessId > 0) ? ChatSession::where('business_id', $this->businessId)->orderByDesc('id')->limit(20)->get() : collect();
        $total = ($this->businessId > 0) ? ChatSession::where('business_id', $this->businessId)->count() : 0;
        $active = ($this->businessId > 0) ? ChatSession::where('business_id', $this->businessId)->where('status', 'active')->count() : 0;
        return view('x-102::customerfacing-widget', compact('sessions', 'total', 'active'));
    }
}
