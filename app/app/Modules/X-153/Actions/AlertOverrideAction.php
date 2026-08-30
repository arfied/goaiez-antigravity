<?php

declare(strict_types=1);

namespace App\Modules\X153\Actions;

use App\Modules\X153\Events\AlertOverridden;
use App\Modules\X153\Models\AlertClaim;
use Illuminate\Support\Facades\Event;

final class AlertOverrideAction
{
    public function handle(int $businessId, int $alertId, int $newUserId): array
    {
        AlertClaim::where('business_id', $businessId)
            ->where('alert_id', $alertId)
            ->update(['claimed_by_user_id' => $newUserId, 'claimed_at' => now()]);

        Event::dispatch(new AlertOverridden(
            businessId: $businessId,
            alertId: $alertId,
            newUserId: $newUserId
        ));

        return [
            'status' => 'overridden',
            'alert_id' => $alertId,
            'new_user_id' => $newUserId,
        ];
    }
}
