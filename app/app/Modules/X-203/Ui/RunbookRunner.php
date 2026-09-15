<?php

declare(strict_types=1);

namespace App\Modules\X203\Ui;

use App\Modules\X203\Models\Runbook;
use App\Modules\X203\Models\RunbookRun;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Runbooks'])]
class RunbookRunner extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        return view('x-203::runbook-runner', [
            'runbooks' => ($this->businessId > 0) ? Runbook::where('business_id', $this->businessId)->orderBy('title')->get() : collect(),
            'runs' => ($this->businessId > 0) ? RunbookRun::where('business_id', $this->businessId)->orderByDesc('id')->limit(20)->get() : collect(),
        ]);
    }
}
