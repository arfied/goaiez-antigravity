<?php

declare(strict_types=1);

namespace App\Modules\X139\Ui;

use App\Modules\X139\Models\ConversionUpload;
use Livewire\Component;

class RejectionRate extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $rejectedCount = ($this->businessId > 0)
            ? ConversionUpload::where('business_id', $this->businessId)->where('status', 'rejected')->count()
            : 0;

        return view('x-139::rejection-rate', [
            'rejectedCount' => $rejectedCount,
        ]);
    }
}
