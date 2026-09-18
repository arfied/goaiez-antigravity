<?php

declare(strict_types=1);

namespace App\Modules\CAgent\Ui;

use App\Modules\CAgent\Models\AgentInstruction;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Agent teaching'])]
class TeachingBox extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $instructions = ($this->businessId > 0)
            ? AgentInstruction::where('business_id', $this->businessId)->get()
            : collect();

        return view('c-agent::teaching-box', [
            'instructions' => $instructions,
        ]);
    }
}
