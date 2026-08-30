<?php

declare(strict_types=1);

namespace App\Modules\X113\Actions;

use App\Modules\X113\Models\StaffUser;

final class StaffAuthenticateCheckAction
{
    /**
     * Checks if staff user is active. REFUSED ON THEIR VERY NEXT REQUEST, not at session expiry (TEST ANCHOR).
     */
    public function authorizeRequest(int $businessId, int $staffUserId): array
    {
        $staff = StaffUser::where('business_id', $businessId)->find($staffUserId);

        if (! $staff || ! $staff->is_active) {
            return [
                'authorized' => false,
                'status' => 'refused',
                'refusal_code' => 'STAFF_USER_DEACTIVATED',
                'message' => 'Staff user was deactivated and is refused on their very next request without waiting for session expiry',
            ];
        }

        return [
            'authorized' => true,
            'status' => 'authorized',
            'staff_user' => $staff,
        ];
    }
}
