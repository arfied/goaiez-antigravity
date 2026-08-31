<?php

declare(strict_types=1);

namespace App\Modules\X204\Ui;

use App\Modules\X204\Models\SendPermit;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RefusalsByReason extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $refusals = ($this->businessId > 0)
            ? SendPermit::where('business_id', $this->businessId)->where('permit_status', 'refused')->get()
            : collect();

        return view('x-204::refusals-by-reason', [
            'refusals' => $refusals,
        ]);
    }
}
