<?php

declare(strict_types=1);

namespace App\Modules\X210\Ui;

use App\Modules\X210\Models\PromotionRedemption;
use Livewire\Component;

class RedemptionsList extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $redemptions = ($this->businessId > 0)
            ? PromotionRedemption::where('business_id', $this->businessId)->latest()->get()
            : collect();

        return view('x-210::redemptions', [
            'redemptions' => $redemptions,
        ]);
    }
}
