<?php

declare(strict_types=1);

namespace App\Modules\X113\Ui;

use App\Modules\X113\Models\Role;
use App\Modules\X113\Models\RolePermission;
use App\Modules\X113\Models\StaffUser;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Document vault'])]
class DocumentVault extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(): void
    {
        $this->businessId = Tenancy::id() ?? 0;
    }

    public function render()
    {
        $staff = collect();

        if ($this->businessId !== 0) {
            $staffList = StaffUser::where('business_id', $this->businessId)->get();
            $roles = Role::where('business_id', $this->businessId)->get()->keyBy('id');
            $permissions = RolePermission::where('business_id', $this->businessId)
                ->where('permission', 'view_employee_documents')
                ->pluck('role_id')
                ->toArray();

            foreach ($staffList as $s) {
                $roleName = 'no role';
                $canView = false;

                if ($s->role_id && $roles->has($s->role_id)) {
                    $roleName = $roles->get($s->role_id)->name;
                    $canView = in_array($s->role_id, $permissions, true);
                }

                $staff->push((object) [
                    'id' => $s->id,
                    'name' => $s->name,
                    'role_name' => $roleName,
                    'can_view' => $canView,
                ]);
            }
        }

        return view('x-113::document-vault', [
            'staff' => $staff,
        ]);
    }

    public function downloadDocument(int $businessId, int $staffUserId, int $documentId): string
    {
        $staff = StaffUser::where('business_id', $businessId)->findOrFail($staffUserId);

        $hasPermission = RolePermission::where('business_id', $businessId)
            ->where('role_id', $staff->role_id)
            ->where('permission', 'view_employee_documents')
            ->exists();

        if (! $hasPermission) {
            throw new \Exception('INSUFFICIENT_ROLE_PERMISSIONS');
        }

        return 'document_content_'.$documentId;
    }
}
