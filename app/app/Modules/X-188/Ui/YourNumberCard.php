<?php

declare(strict_types=1);

namespace App\Modules\X188\Ui;

use App\Modules\X188\Models\NumberAssignment;
use App\Modules\X188\Models\NumberPool;
use Livewire\Component;

class YourNumberCard extends Component
{
    public function render()
    {
        // The number_assignments table enforces Row Level Security via a tenant_isolation
        // policy, which safely scopes this query to the current app.business_id set by the ResolveTenant middleware.
        $assignment = NumberAssignment::where('status', 'active')->first();
        $number = $assignment ? NumberPool::find($assignment->phone_number_id)?->phone_number : null;

        return view('x-188::your-number-card', [
            'assignment' => $assignment,
            'number' => $number,
        ]);
    }
}
