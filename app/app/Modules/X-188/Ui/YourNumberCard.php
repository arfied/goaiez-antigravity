<?php

declare(strict_types=1);

namespace App\Modules\X188\Ui;

use App\Modules\X188\Models\NumberAssignment;
use App\Modules\X188\Models\NumberPool;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Your number'])]
class YourNumberCard extends Component
{
    public function render()
    {
        // RLS is ENABLED and FORCED on the number_assignments table, applying the tenant_isolation policy
        // even to table owners. The policy evaluates `business_id = nullif(current_setting('app.business_id', true), '')`,
        // which rejects all rows if the setting is missing or empty. This guarantees the unscoped query
        // is safely bound to the tenant set by the ResolveTenant middleware.
        $assignment = NumberAssignment::where('status', 'active')->first();
        $number = $assignment ? NumberPool::find($assignment->phone_number_id)?->phone_number : null;

        return view('x-188::your-number-card', [
            'assignment' => $assignment,
            'number' => $number,
        ]);
    }
}
