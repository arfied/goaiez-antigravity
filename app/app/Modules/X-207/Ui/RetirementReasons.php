<?php

declare(strict_types=1);

namespace App\Modules\X207\Ui;

use App\Modules\X207\Models\DeviceToken;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Retired devices'])]
class RetirementReasons extends Component
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
        return view('x-207::retirement-reasons', [
            'tokens' => ($this->businessId > 0) ? DeviceToken::where('business_id', $this->businessId)->where('status', 'retired')->orderByDesc('id')->get() : collect(),
        ]);
    }
}
