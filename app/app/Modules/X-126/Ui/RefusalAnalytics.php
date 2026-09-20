<?php

declare(strict_types=1);

namespace App\Modules\X126\Ui;

use App\Modules\X126\Actions\GetCapabilityDecisionsAction;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Capability refusals'])]
class RefusalAnalytics extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render(GetCapabilityDecisionsAction $action)
    {
        $refusals = ($this->businessId > 0)
            ? $action->handle($this->businessId)
            : collect();

        return view('x-126::refusal-analytics', [
            'refusals' => $refusals,
        ]);
    }
}
