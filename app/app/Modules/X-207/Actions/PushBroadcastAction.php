<?php

declare(strict_types=1);

namespace App\Modules\X207\Actions;

use App\Modules\X207\Models\DeviceToken;

final class PushBroadcastAction
{
    public function __construct(private readonly PushSendAction $sendAction) {}

    public function handle(int $businessId, array $rawPayload): array
    {
        $devices = DeviceToken::where('business_id', $businessId)->where('status', 'active')->get();
        $delivered = 0;

        foreach ($devices as $dev) {
            $this->sendAction->handle($businessId, $dev->id, $rawPayload, true);
            $delivered++;
        }

        return [
            'status' => 'broadcast_complete',
            'recipient_count' => $delivered,
        ];
    }

    public function toUser(int $businessId, int $userId, array $rawPayload): array
    {
        $devices = DeviceToken::where('business_id', $businessId)
            ->where('user_id', $userId)
            ->where('platform', 'web')
            ->where('status', 'active')
            ->get();

        $sent = 0;
        $failed = 0;
        $expired = 0;

        foreach ($devices as $dev) {
            $result = $this->sendAction->handle($businessId, $dev->id, $rawPayload, true);
            if ($result['status'] === 'sent') {
                $sent++;
            } elseif ($result['status'] === 'expired') {
                $expired++;
            } else {
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'expired' => $expired];
    }

    public function activeWebDevices(int $businessId, int $userId): int
    {
        return DeviceToken::where('business_id', $businessId)
            ->where('user_id', $userId)
            ->where('platform', 'web')
            ->where('status', 'active')
            ->count();
    }
}
