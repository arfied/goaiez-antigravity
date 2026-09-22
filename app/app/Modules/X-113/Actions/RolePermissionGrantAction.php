<?php

declare(strict_types=1);

namespace App\Modules\X113\Actions;

use App\Modules\X113\Models\Role;
use App\Modules\X113\Models\RolePermission;
use InvalidArgumentException;

final class RolePermissionGrantAction
{
    public const PERMISSIONS = ['view_employee_documents'];

    public function handle(int $businessId, int $roleId, string $permission): RolePermission
    {
        if (! in_array($permission, self::PERMISSIONS, true)) {
            throw new InvalidArgumentException("Unknown permission: '{$permission}'");
        }

        $role = Role::where('business_id', $businessId)->find($roleId);
        if (! $role) {
            throw new InvalidArgumentException('Invalid role.');
        }

        if (RolePermission::where('business_id', $businessId)
            ->where('role_id', $roleId)
            ->where('permission', $permission)
            ->exists()) {
            throw new InvalidArgumentException("Role already has the '{$permission}' permission.");
        }

        return RolePermission::create([
            'business_id' => $businessId,
            'role_id' => $roleId,
            'permission' => $permission,
        ]);
    }
}
