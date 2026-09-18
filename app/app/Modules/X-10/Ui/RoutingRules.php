<?php

declare(strict_types=1);

namespace App\Modules\X10\Ui;

use App\Modules\X10\Models\RoutingRule;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Lead routing rules'])]
class RoutingRules extends Component
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
        $rules = ($this->businessId > 0)
            ? RoutingRule::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-10::routing-rules', [
            'rules' => $rules,
        ]);
    }
}
