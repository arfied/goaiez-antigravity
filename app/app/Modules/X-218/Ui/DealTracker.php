<?php

declare(strict_types=1);

namespace App\Modules\X218\Ui;

use App\Modules\X218\Models\InfluencerDeal;
use Livewire\Attributes\Locked;
use Livewire\Component;

class DealTracker extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $deals = ($this->businessId > 0)
            ? InfluencerDeal::where('business_id', $this->businessId)->with('deliverables')->get()
            : collect();

        return view('x-218::deal-tracker', [
            'deals' => $deals,
        ]);
    }
}
