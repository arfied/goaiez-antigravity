<?php

declare(strict_types=1);

namespace App\Modules\X204\Ui;

use App\Modules\X204\Actions\GetRefusalsAction;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Consent Refusals by Reason'])]
class RefusalsByReason extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount()
    {
        $this->businessId = Tenancy::id() ?? 0;
    }

    public function render(GetRefusalsAction $action)
    {
        $refusals = ($this->businessId > 0)
            ? $action->handle($this->businessId)
            : collect();

        return view('x-204::refusals-by-reason', [
            'refusals' => $refusals,
        ]);
    }
}
