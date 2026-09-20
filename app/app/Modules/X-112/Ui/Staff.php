<?php

declare(strict_types=1);

namespace App\Modules\X112\Ui;

use App\Modules\X112\Actions\GetStaffRolesAction;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Agency staff'])]
class Staff extends Component
{
    public function render(GetStaffRolesAction $action)
    {
        abort_unless(Tenancy::check(), 403);
        $staff = $action->handle(Tenancy::idOrFail());

        return view('x-112::staff', [
            'staff' => $staff,
        ]);
    }
}
