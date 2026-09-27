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

    public function registerWebSubscription(int $businessId, int $userId, array $subscription): DeviceToken
    {
        $endpoint = $subscription['endpoint'] ?? '';
        $p256dh = $subscription['keys']['p256dh'] ?? '';
        $auth = $subscription['keys']['auth'] ?? '';

        if (! str_starts_with($endpoint, 'https://') || $p256dh === '' || $auth === '') {
            throw new \InvalidArgumentException('That browser did not hand over a usable subscription.');
        }

        return DeviceToken::updateOrCreate(
            ['business_id' => $businessId, 'device_token' => hash('sha256', $endpoint)],
            [
                'platform' => 'web',
                'user_id' => $userId,
                'subscription' => [
                    'endpoint' => $endpoint,
                    'keys' => [
                        'p256dh' => $p256dh,
                        'auth' => $auth,
                    ],
                ],
                'status' => 'active',
                'retirement_reason' => null,
            ]
        );
    }
}
