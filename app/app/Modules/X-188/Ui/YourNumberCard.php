<?php

declare(strict_types=1);

namespace App\Modules\X188\Ui;

use App\Modules\X188\Models\NumberAssignment;
use Livewire\Attributes\Locked;
use Livewire\Component;

class YourNumberCard extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $assignment = ($this->businessId > 0)
            ? NumberAssignment::where('business_id', $this->businessId)->where('status', 'active')->first()
            : null;

        return view('x-188::your-number-card', [
            'assignment' => $assignment,
        ]);
    }
}
