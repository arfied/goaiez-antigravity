<?php

declare(strict_types=1);

namespace App\Modules\X112\Ui;

use App\Modules\X112\Actions\GetStaffRolesAction;
use App\Modules\X112\Actions\StaffInviteAction;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Agency staff'])]
class Staff extends Component
{
    public int $agencyId = 0;
    public int $userId = 0;
    public string $role = 'account_manager';
    public ?string $success = null;
    public ?string $error = null;

    public function inviteStaff(StaffInviteAction $action): void
    {
        $this->success = null;
        $this->error = null;

        if ($this->agencyId === 0) {
            $this->error = 'Agency ID is required.';

            return;
        }

        if ($this->userId === 0) {
            $this->error = 'User ID is required.';

            return;
        }

        $staff = $action->handle(Tenancy::idOrFail(), $this->agencyId, $this->userId, $this->role);

        $this->success = "Invited user {$staff->user_id} as {$staff->role} under agency {$staff->agency_id}. Nothing downstream is wired to it yet.";
        $this->agencyId = 0;
        $this->userId = 0;
        $this->role = 'account_manager';
    }

    public function render(GetStaffRolesAction $action)
    {
        abort_unless(Tenancy::check(), 403);
        $staff = $action->handle(Tenancy::idOrFail());

        return view('x-112::staff', [
            'staff' => $staff,
        ]);
    }
}
