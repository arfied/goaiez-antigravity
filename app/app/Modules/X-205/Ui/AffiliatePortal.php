<?php

declare(strict_types=1);

namespace App\Modules\X205\Ui;

use App\Modules\X205\Models\Affiliate;
use Livewire\Component;

class AffiliatePortal extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $affiliates = ($this->businessId > 0)
            ? Affiliate::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-205::portal', [
            'affiliates' => $affiliates,
        ]);
    }
}
