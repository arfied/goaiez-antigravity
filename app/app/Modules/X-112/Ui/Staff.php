<?php

declare(strict_types=1);

namespace App\Modules\X112\Ui;

use App\Modules\X112\Models\StaffRole;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Staff'])]
class Staff extends Component
{
    public function render()
    {
        abort_unless(Tenancy::check(), 403);
        $staff = StaffRole::where('business_id', Tenancy::idOrFail())->get();

        return view('x-112::staff', [
            'staff' => $staff,
        ]);
    }
}
