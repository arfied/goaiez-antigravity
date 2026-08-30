<?php

declare(strict_types=1);

namespace App\Modules\X207\Actions;

use App\Modules\X207\Models\DeviceToken;

final class PushRegisterDeviceAction
{
    public function handle(
        int $businessId,
        string $deviceToken,
        string $platform = 'ios',
        ?int $personId = null
    ): DeviceToken {
        return DeviceToken::updateOrCreate(
            ['business_id' => $businessId, 'device_token' => $deviceToken],
            [
                'person_id' => $personId,
                'platform' => $platform,
                'status' => 'active',
                'retirement_reason' => null,
            ]
        );
    }
}
