<?php

declare(strict_types=1);

namespace App\Modules\X113\Actions;

use App\Modules\X113\Models\Role;
use App\Modules\X113\Models\StaffUser;

final class StaffRosterAction
{
    public function handle(int $businessId): array
    {
        $staff = StaffUser::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $roleIds = $staff->pluck('role_id')->filter()->unique();
        $roles = Role::whereIn('id', $roleIds)->get()->keyBy('id');

        $roster = [];
        foreach ($staff as $user) {
            $roleName = 'Staff';
            if ($user->role_id && isset($roles[$user->role_id])) {
                $roleName = $roles[$user->role_id]->name;
            }
            $roster[] = [
                'name' => $user->name,
                'role' => $roleName,
            ];
        }

        return $roster;
    }
}
