<?php

declare(strict_types=1);

namespace App\Modules\X207\Actions;

use App\Modules\X207\Models\DeviceToken;

final class PushRetireDeviceAction
{
    public function handle(int $businessId, string $deviceToken, string $reason = 'unregistered'): DeviceToken
    {
        $device = DeviceToken::where('business_id', $businessId)->where('device_token', $deviceToken)->firstOrFail();
        $device->update([
            'status' => 'retired',
            'retirement_reason' => $reason,
        ]);

        return $device;
    }
}
