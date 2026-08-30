<?php

declare(strict_types=1);

namespace App\Modules\X205\Ui;

use App\Modules\X205\Models\AffiliatePayout;
use Livewire\Component;

class PayoutRunView extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $payouts = ($this->businessId > 0)
            ? AffiliatePayout::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-205::payout-run', [
            'payouts' => $payouts,
        ]);
    }
}
