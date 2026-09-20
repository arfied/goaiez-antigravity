<?php

declare(strict_types=1);

namespace App\Modules\X161\Ui;

use App\Modules\X161\Models\DemoLedger;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class DemoLedgerDaily extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $entries = ($this->businessId > 0)
            ? DemoLedger::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-161::demo-ledger-daily', [
            'entries' => $entries,
        ]);
    }
}
