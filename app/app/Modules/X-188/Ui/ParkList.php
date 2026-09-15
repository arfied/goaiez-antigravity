<?php

declare(strict_types=1);

namespace App\Modules\X188\Ui;

use App\Modules\X188\Models\NumberPark;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Parked numbers'])]
class ParkList extends Component
{
    public function render()
    {
        // RLS is ENABLED and FORCED on the number_parks table, applying the tenant_isolation policy
        // even to table owners. The policy evaluates `business_id = nullif(current_setting('app.business_id', true), '')`,
        // which rejects all rows if the setting is missing or empty. This guarantees the unscoped query
        // is safely bound to the tenant set by the ResolveTenant middleware.
        // We filter by `is_released: false` to exclude numbers no longer parked. This is currently a no-op
        // as no code sets it to true yet, but it enforces the screen's purpose of showing only active parks.
        $parks = NumberPark::where('is_released', false)->get();

        return view('x-188::park-list', [
            'parks' => $parks,
        ]);
    }
}
