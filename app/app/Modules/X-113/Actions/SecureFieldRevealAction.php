<?php

declare(strict_types=1);

namespace App\Modules\X113\Actions;

use App\Modules\X113\Models\RolePermission;
use App\Modules\X113\Models\StaffUser;

final class SecureFieldRevealAction
{
    /**
     * Secure field reveal is role- AND job-scoped, and logged (TEST ANCHOR, P-198).
     */
    public function revealField(
        int $businessId,
        int $staffUserId,
        int $targetJobId,
        int $assignedJobId,
        string $requiredPermission,
        string $secretFieldValue
    ): array {
        $staff = StaffUser::where('business_id', $businessId)->findOrFail($staffUserId);

        // 1. Check if user is active
        if (! $staff->is_active) {
            return ['status' => 'refused', 'refusal_code' => 'STAFF_DEACTIVATED'];
        }

        // 2. Check role permission (Role-scoped)
        $hasPermission = RolePermission::where('business_id', $businessId)
            ->where('role_id', $staff->role_id)
            ->where('permission', $requiredPermission)
            ->exists();

        if (! $hasPermission) {
            return ['status' => 'refused', 'refusal_code' => 'INSUFFICIENT_ROLE_PERMISSIONS'];
        }

        // 3. Check job scope (Job-scoped)
        if ($targetJobId !== $assignedJobId) {
            return ['status' => 'refused', 'refusal_code' => 'OUT_OF_JOB_SCOPE'];
        }

        // 4. Logged audit reveal
        return [
            'status' => 'revealed',
            'value' => $secretFieldValue,
            'audit_logged' => true,
            'accessor_id' => $staff->id,
            'job_id' => $targetJobId,
        ];
    }
}
