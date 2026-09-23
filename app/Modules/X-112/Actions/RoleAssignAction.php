<?php

declare(strict_types=1);

namespace App\Modules\X112\Actions;

use App\Modules\X112\Models\StaffRole;

final class RoleAssignAction
{
    public function handle(int $businessId, int $agencyId, int $userId, string $role): StaffRole
    {
        return StaffRole::updateOrCreate(
            ['business_id' => $businessId, 'agency_id' => $agencyId, 'user_id' => $userId],
            ['role' => $role, 'is_active' => true]
        );
    }
}
