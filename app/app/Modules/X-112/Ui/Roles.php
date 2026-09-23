<?php

declare(strict_types=1);

namespace App\Modules\X112\Ui;

use App\Modules\X112\Actions\GetStaffRolesAction;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.agency')]
class Roles extends Component
{
    public function render(GetStaffRolesAction $action)
    {
        $roles = Tenancy::check() ? $action->handle(Tenancy::idOrFail()) : collect();

        return view('x-112::roles', ['roles' => $roles]);
    }
}
