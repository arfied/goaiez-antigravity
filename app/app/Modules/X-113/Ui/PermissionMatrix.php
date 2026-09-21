<?php

declare(strict_types=1);

namespace App\Modules\X113\Ui;

use App\Modules\X113\Actions\RolePermissionGrantAction;
use App\Modules\X113\Models\Role;
use App\Modules\X113\Models\RolePermission;
use App\Support\Tenancy;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Permissions'])]
class PermissionMatrix extends Component
{
    #[Locked]
    public int $businessId = 0;

    public int $roleId = 0;

    public string $permission = '';

    public ?string $error = null;

    public ?string $success = null;

    public function mount(): void
    {
        $this->businessId = Tenancy::id() ?? 0;
    }

    public function grantPermission(RolePermissionGrantAction $action): void
    {
        $this->error = null;
        $this->success = null;

        if ($this->roleId === 0) {
            $this->error = 'Please select a role.';

            return;
        }

        try {
            $action->handle(Tenancy::idOrFail(), $this->roleId, $this->permission);

            $role = Role::where('business_id', Tenancy::idOrFail())->findOrFail($this->roleId);

            $this->success = "Granted permission '{$this->permission}' to role '{$role->name}'. The only thing reading this grant today is the document vault's download check.";
            $this->roleId = 0;
            $this->permission = '';
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        $roles = collect();
        $grants = collect();

        if ($this->businessId > 0) {
            $roles = Role::where('business_id', $this->businessId)->orderBy('name')->get();
            $grants = RolePermission::where('business_id', $this->businessId)->get()->groupBy('role_id');
        }

        return view('x-113::permission-matrix', [
            'roles' => $roles,
            'grants' => $grants,
        ]);
    }
}
