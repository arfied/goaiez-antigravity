<?php

declare(strict_types=1);

namespace App\Modules\X139\Ui;

use App\Modules\X139\Models\ConversionUpload;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RejectionRate extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $rejectedCount = 0;
        $totalCount = 0;
        $reasons = collect();

        if ($this->businessId > 0) {
            $query = ConversionUpload::where('business_id', $this->businessId);
            $totalCount = $query->count();
            
            $rejectedQuery = clone $query;
            $rejectedQuery->where('status', 'rejected');
            $rejectedCount = $rejectedQuery->count();
            
            $reasons = $rejectedQuery->whereNotNull('rejection_reason')->pluck('rejection_reason');
        }

        $rate = $totalCount > 0 ? ($rejectedCount / $totalCount) * 100 : 0;

        return view('x-139::rejection-rate', [
            'rejectedCount' => $rejectedCount,
            'rate' => $rate,
            'reasons' => $reasons,
        ]);
    }
}
