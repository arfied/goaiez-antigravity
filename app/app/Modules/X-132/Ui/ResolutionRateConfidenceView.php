<?php

declare(strict_types=1);

namespace App\Modules\X132\Ui;

use App\Modules\X132\Models\PersonLink;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ResolutionRateConfidenceView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $links = ($this->businessId > 0)
            ? PersonLink::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-132::resolution-rate-confidence', [
            'links' => $links,
        ]);
    }
}
