<?php

declare(strict_types=1);

namespace App\Modules\X168\Ui;

use App\Modules\X168\Models\Timesheet;
use Livewire\Component;

class ApprovalsView extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $pending = ($this->businessId > 0)
            ? Timesheet::where('business_id', $this->businessId)->where('status', 'submitted')->get()
            : collect();

        return view('x-168::approvals', [
            'pending' => $pending,
        ]);
    }
}
