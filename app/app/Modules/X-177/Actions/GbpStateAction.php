<?php

declare(strict_types=1);

namespace App\Modules\X177\Actions;

use App\Modules\X177\Events\GbpReinstated;
use App\Modules\X177\Events\GbpSuspended;
use App\Modules\X177\Models\GbpConnection;
use App\Modules\X177\Models\GbpStateLog;
use Illuminate\Support\Facades\Event;

final class GbpStateAction
{
    /**
     * Polls profile state / fallback when webhook has not arrived (TEST ANCHOR).
     */
    public function pollState(int $businessId, int $connectionId, ?string $mockRemoteStatus = null): array
    {
        $conn = GbpConnection::where('business_id', $businessId)->findOrFail($connectionId);

        $newStatus = $mockRemoteStatus ?? $conn->profile_status;
        $oldStatus = $conn->profile_status;

        if ($newStatus !== $oldStatus) {
            $conn->update(['profile_status' => $newStatus]);

            if ($newStatus === 'suspended') {
                Event::dispatch(new GbpSuspended($businessId, $conn->id));
            } elseif ($newStatus === 'active') {
                Event::dispatch(new GbpReinstated($businessId, $conn->id));
            }
        }

        GbpStateLog::create([
            'business_id' => $businessId,
            'connection_id' => $conn->id,
            'event_type' => 'state_read',
            'details' => ['old_status' => $oldStatus, 'new_status' => $newStatus],
        ]);

        return [
            'status' => 'state_read_completed',
            'profile_status' => $conn->profile_status,
        ];
    }
}
