<?php

declare(strict_types=1);

namespace App\Modules\X206\Ui;

use App\Modules\X206\Models\CredentialReveal;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Reveal log'])]
class RevealLog extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId ?: Tenancy::id() ?? 0;
        abort_if($this->businessId === 0, 404);
    }

    public function render()
    {
        $logs = ($this->businessId > 0)
            ? CredentialReveal::where('business_id', $this->businessId)->orderBy('id', 'desc')->get()
            : collect();

        return view('x-206::reveal-log', [
            'logs' => $logs,
        ]);
    }
}
