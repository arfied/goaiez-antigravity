<?php

declare(strict_types=1);

namespace App\Modules\X126\Ui;

use App\Modules\X126\Models\CapabilityDecision;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RefusalAnalytics extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $refusals = ($this->businessId > 0)
            ? CapabilityDecision::where('business_id', $this->businessId)
                ->where('decision', '!=', 'permitted')
                ->orderBy('id', 'desc')
                ->get()
            : collect();

        return view('x-126::refusal-analytics', [
            'refusals' => $refusals,
        ]);
    }
}
