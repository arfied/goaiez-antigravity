<?php

declare(strict_types=1);

namespace App\Modules\X190\Actions;

use App\Modules\X190\Models\PartnerPool;
use App\Modules\X190\Models\ReferralSlot;

final class SlotDeclineAction
{
    public function decline(int $businessId, int $slotId, int $partnerId): void
    {
        $slot = ReferralSlot::where('business_id', $businessId)->findOrFail($slotId);
        $slot->update(['status' => 'declined']);

        $partner = PartnerPool::where('business_id', $businessId)->findOrFail($partnerId);
        $partner->update(['is_declined' => true]);
    }
}
