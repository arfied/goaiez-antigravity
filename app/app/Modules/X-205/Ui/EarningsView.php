<?php

declare(strict_types=1);

namespace App\Modules\X205\Ui;

use App\Modules\X205\Models\AffiliateAttribution;
use Livewire\Attributes\Locked;
use Livewire\Component;

class EarningsView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $attributions = ($this->businessId > 0)
            ? AffiliateAttribution::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-205::earnings', [
            'attributions' => $attributions,
        ]);
    }
}
