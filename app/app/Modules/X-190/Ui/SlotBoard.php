<?php

declare(strict_types=1);

namespace App\Modules\X190\Ui;

use App\Modules\X190\Models\ReferralSlot;
use Livewire\Attributes\Locked;
use Livewire\Component;

class SlotBoard extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $slots = ($this->businessId > 0)
            ? ReferralSlot::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-190::slot-board', [
            'slots' => $slots,
        ]);
    }
}
