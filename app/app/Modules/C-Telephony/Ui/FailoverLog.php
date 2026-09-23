<?php

declare(strict_types=1);

namespace App\Modules\CTelephony\Ui;

use App\Modules\CTelephony\Models\CarrierReceipt;
use Livewire\Attributes\Locked;
use Livewire\Component;
use App\Support\Tenancy;

class FailoverLog extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $receipts = ($this->businessId > 0)
            ? CarrierReceipt::where('business_id', $this->businessId)->orderBy('id', 'desc')->get()
            : collect();

        return view('c-telephony::failover-log', [
            'receipts' => $receipts,
        ]);
    }
}
