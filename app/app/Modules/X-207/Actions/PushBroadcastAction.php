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
}
