<?php

declare(strict_types=1);

namespace App\Modules\X113\Actions;

use App\Modules\X113\Events\RoleAssigned;
use App\Modules\X113\Models\Role;
use App\Modules\X113\Models\StaffUser;
use Illuminate\Support\Facades\Event;

final class RoleAssignAction
{
    public function handle(int $businessId, int $staffUserId, int $roleId): StaffUser
    {
        $staff = StaffUser::where('business_id', $businessId)->findOrFail($staffUserId);
        $role = Role::where('business_id', $businessId)->findOrFail($roleId);

        $staff->update(['role_id' => $role->id]);

        Event::dispatch(new RoleAssigned($businessId, $staff->id, $role->id));

        return $staff;
    }
}
