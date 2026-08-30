<?php

declare(strict_types=1);

namespace App\Modules\X190\Actions;

use App\Modules\X190\Events\ApprovalRequested;
use App\Modules\X190\Models\PartnerPool;
use App\Modules\X190\Models\ReferralSlot;
use Illuminate\Support\Facades\Event;

final class SlotProposeAction
{
    /**
     * Proposes a partner for a territory slot.
     * TEST ANCHOR: A declined partner's research row is reused for the next proposal in the same territory, not re-fetched.
     */
    public function proposePartner(
        int $businessId,
        int $slotId,
        string $companyName,
        string $category,
        string $territoryZip,
        array $newResearchData = []
    ): PartnerPool {
        $slot = ReferralSlot::where('business_id', $businessId)->findOrFail($slotId);

        // Check if existing partner row in pool exists in this territory
        $existing = PartnerPool::where('business_id', $businessId)
            ->where('category', $category)
            ->where('territory_zip', $territoryZip)
            ->where('company_name', $companyName)
            ->first();

        if ($existing) {
            // TEST ANCHOR: Reused research row, NOT re-fetched
            $partner = $existing;
        } else {
            $partner = PartnerPool::create([
                'business_id' => $businessId,
                'company_name' => $companyName,
                'category' => $category,
                'territory_zip' => $territoryZip,
                'research_data' => $newResearchData,
                'fetch_count' => 1,
                'is_declined' => false,
            ]);
        }

        $slot->update(['status' => 'proposed']);

        Event::dispatch(new ApprovalRequested($businessId, $slot->id, $partner->id));

        return $partner;
    }
}
