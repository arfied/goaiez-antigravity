<?php

declare(strict_types=1);

namespace App\Modules\X108\Ui;

use App\Modules\X108\Models\Waitlist as WaitlistModel;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Waitlist extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $waitlists = ($this->businessId > 0)
            ? WaitlistModel::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-108::waitlist', [
            'waitlists' => $waitlists,
        ]);
    }
}
