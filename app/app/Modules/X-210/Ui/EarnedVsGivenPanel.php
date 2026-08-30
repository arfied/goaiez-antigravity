<?php

declare(strict_types=1);

namespace App\Modules\X210\Ui;

use App\Modules\X210\Models\PromotionRedemption;
use Livewire\Component;

class EarnedVsGivenPanel extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $totalDiscount = ($this->businessId > 0)
            ? PromotionRedemption::where('business_id', $this->businessId)->sum('discount_applied_cents')
            : 0;

        return view('x-210::earnedvsgiven-panel', [
            'totalDiscount' => $totalDiscount,
        ]);
    }
}
