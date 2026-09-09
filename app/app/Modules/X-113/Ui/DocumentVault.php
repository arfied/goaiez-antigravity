<?php

declare(strict_types=1);

namespace App\Modules\X113\Ui;

use App\Modules\X113\Models\RolePermission;
use App\Modules\X113\Models\StaffUser;
use Livewire\Component;

class DocumentVault extends Component
{
    public function render()
    {
        return view('x-113::document-vault');
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
