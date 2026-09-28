<?php

declare(strict_types=1);

namespace App\Modules\X113\Ui;

use App\Enums\UserRole;
use App\Modules\X113\Actions\RoleAssignAction;
use App\Modules\X113\Actions\StaffInviteAction;
use App\Modules\X113\Models\Role;
use App\Modules\X113\Models\StaffUser;
use App\Services\Team\TeamInvites;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Staff'])]
class Staff extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $name = '';

    public string $email = '';

    public string $inviteRole = 'staff';

    public ?string $success = null;

    public ?string $error = null;

    public string $assignStaffId = '';

    public string $assignRoleId = '';

    public ?string $assignSuccess = null;

    public ?string $assignError = null;

    public function mount(): void
    {
        $this->businessId = Tenancy::id() ?? 0;
    }

    public function invite(StaffInviteAction $action): void
    {
        abort_unless(auth()->user()?->hasRole(UserRole::Owner) === true, 403);

        $this->success = null;
        $this->error = null;

        $name = trim($this->name);
        $email = trim($this->email);

        if ($email === '') {
            $this->error = 'Email is required.';

            return;
        }

        if ($name === '') {
            $this->error = 'Name is required.';

            return;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error = 'That is not a valid email address.';

            return;
        }

        $businessId = Tenancy::idOrFail();

        $exists = StaffUser::where('business_id', $businessId)->where('email', $email)->exists();
        if ($exists) {
            $this->error = 'This email is already on the crew.';

            return;
        }

        if ($this->inviteRole === 'manager') {
            $mappedRole = UserRole::Manager;
        } elseif ($this->inviteRole === 'staff') {
            $mappedRole = UserRole::Staff;
        } else {
            $this->error = 'Choose Staff or Manager.';

            return;
        }

        $result = app(TeamInvites::class)->invite($businessId, $email, $name, auth()->user(), $mappedRole);

        if ($result['ok'] === false) {
            $this->error = $result['message'];

            return;
        }

        $action->handle($businessId, $email, $name, null);

        $this->success = $result['message'];

        $this->name = '';
        $this->email = '';
        $this->inviteRole = 'staff';
    }

    public function assignRole(RoleAssignAction $action): void
    {
        $this->assignSuccess = null;
        $this->assignError = null;

        if (trim($this->assignStaffId) === '') {
            $this->assignError = 'Choose a crew member.';

            return;
        }

        if (trim($this->assignRoleId) === '') {
            $this->assignError = 'Choose a role.';

            return;
        }

        $staff = $action->handle(Tenancy::idOrFail(), (int) $this->assignStaffId, (int) $this->assignRoleId);
        $role = Role::where('business_id', Tenancy::idOrFail())->find($staff->role_id);

        $this->assignSuccess = 'Assigned '.$role->name.' to '.$staff->name
            .'. The document vault reads this role when it decides who can see employee documents.';

        $this->assignStaffId = '';
        $this->assignRoleId = '';
    }

    public ?string $accessSuccess = null;

    public ?string $accessError = null;

    public function resendInvite(int $membershipId): void
    {
        abort_unless(auth()->user()?->hasRole(UserRole::Owner) === true, 403);

        $this->accessSuccess = null;
        $this->accessError = null;

        $result = app(TeamInvites::class)->resend($membershipId, auth()->user());

        if ($result['ok']) {
            $this->accessSuccess = $result['message'];
        } else {
            $this->accessError = $result['message'];
        }
    }

    public function revokeAccess(int $membershipId): void
    {
        abort_unless(auth()->user()?->hasRole(UserRole::Owner) === true, 403);

        $this->accessSuccess = null;
        $this->accessError = null;

        $result = app(TeamInvites::class)->revoke($membershipId, auth()->user());

        if ($result['ok']) {
            $this->accessSuccess = $result['message'];
        } else {
            $this->accessError = $result['message'];
        }
    }

    public function render()
    {
        $staff = ($this->businessId > 0)
            ? StaffUser::where('business_id', $this->businessId)->orderBy('name')->get()
            : collect();

        $roles = ($this->businessId > 0)
            ? Role::where('business_id', $this->businessId)->orderBy('name')->get()
            : collect();

        $isOwner = auth()->user()?->hasRole(UserRole::Owner) === true;
        $members = $isOwner ? app(TeamInvites::class)->members() : collect();

        return view('x-113::staff', [
            'staff' => $staff,
            'roles' => $roles,
            'members' => $members,
        ]);
    }
}
