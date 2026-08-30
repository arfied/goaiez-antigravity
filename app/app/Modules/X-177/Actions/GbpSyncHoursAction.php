<?php

declare(strict_types=1);

namespace App\Modules\X177\Actions;

use App\Modules\X177\Models\GbpConnection;
use App\Modules\X177\Models\GbpStateLog;

final class GbpSyncHoursAction
{
    public function sync(int $businessId, int $connectionId, array $operatingHours): array
    {
        $conn = GbpConnection::where('business_id', $businessId)->findOrFail($connectionId);

        GbpStateLog::create([
            'business_id' => $businessId,
            'connection_id' => $conn->id,
            'event_type' => 'hours_synced',
            'details' => $operatingHours,
        ]);

        return [
            'status' => 'hours_synced',
            'connection_id' => $conn->id,
            'hours' => $operatingHours,
        ];
    }
}
