<?php

declare(strict_types=1);

namespace App\Modules\X201\Ui;

use App\Modules\X201\Models\Dispute;
use Livewire\Component;

class DisputeCard extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $disputes = ($this->businessId > 0)
            ? Dispute::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-201::dispute-card', [
            'disputes' => $disputes,
        ]);
    }
}
