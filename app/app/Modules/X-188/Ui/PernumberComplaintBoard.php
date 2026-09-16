<?php

declare(strict_types=1);

namespace App\Modules\X188\Ui;

use App\Modules\X188\Models\NumberPool;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Number complaints'])]
class PernumberComplaintBoard extends Component
{
    public function render()
    {
        // RLS is ENABLED and FORCED on the number_pool table, applying the tenant_isolation policy
        // even to table owners. The policy evaluates `business_id = nullif(current_setting('app.business_id', true), '')`,
        // which rejects all rows if the setting is missing or empty. This guarantees the unscoped query
        // is safely bound to the tenant set by the ResolveTenant middleware.
        return view('x-188::pernumber-complaint-board', [
            'numbers' => NumberPool::where('complaint_count', '>', 0)->orderByDesc('complaint_count')->get(),
        ]);
    }
}
