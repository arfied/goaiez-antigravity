<?php

declare(strict_types=1);

namespace App\Modules\X210\Ui;

use App\Modules\X210\Models\PromotionRedemption;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Discounts given'])]
class EarnedVsGivenPanel extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId ?: Tenancy::id() ?? 0;
        abort_if($this->businessId === 0, 404);
    }

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
