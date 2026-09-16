<?php

declare(strict_types=1);

namespace App\Modules\X205\Ui;

use App\Modules\X205\Models\AffiliateAttribution;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Affiliate earnings'])]
class EarningsView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $attributions = ($this->businessId > 0)
            ? AffiliateAttribution::where('business_id', $this->businessId)->orderByDesc('attributed_at')->get()
            : collect();

        return view('x-205::earnings', [
            'attributions' => $attributions,
        ]);
    }
}
