<?php

declare(strict_types=1);

namespace App\Modules\X205\Ui;

use App\Modules\X205\Models\AffiliatePayout;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Affiliate payouts'])]
class PayoutRunView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $payouts = ($this->businessId > 0)
            ? AffiliatePayout::where('business_id', $this->businessId)->orderByDesc('id')->get()
            : collect();

        return view('x-205::payout-run', [
            'payouts' => $payouts,
        ]);
    }
}
