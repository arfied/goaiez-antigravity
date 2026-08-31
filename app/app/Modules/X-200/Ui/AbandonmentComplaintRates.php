<?php

declare(strict_types=1);

namespace App\Modules\X200\Ui;

use App\Modules\X200\Models\CallCampaign;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AbandonmentComplaintRates extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $campaigns = ($this->businessId > 0)
            ? CallCampaign::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-200::abandonment-complaint-rates', [
            'campaigns' => $campaigns,
        ]);
    }
}
