<?php

declare(strict_types=1);

namespace App\Modules\X210\Ui;

use App\Modules\X210\Models\Promotion;
use App\Modules\X210\Models\PromotionRedemption;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Your offers used'])]
class RedemptionsList extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $redemptions = ($this->businessId > 0)
            ? PromotionRedemption::where('business_id', $this->businessId)->latest()->get()
            : collect();

        $codes = Promotion::where('business_id', $this->businessId)->whereIn('id', $redemptions->pluck('promotion_id'))->pluck('code', 'id');

        return view('x-210::redemptions', [
            'redemptions' => $redemptions,
            'codes' => $codes,
        ]);
    }
}
