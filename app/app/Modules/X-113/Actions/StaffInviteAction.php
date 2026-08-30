<?php

declare(strict_types=1);

namespace App\Modules\X113\Actions;

use App\Modules\X113\Models\StaffUser;

final class StaffInviteAction
{
    public function handle(int $businessId, string $email, string $name, ?int $roleId = null): StaffUser
    {
        return StaffUser::create([
            'business_id' => $businessId,
            'email' => $email,
            'name' => $name,
            'role_id' => $roleId,
            'is_active' => true,
        ]);
    }
}
