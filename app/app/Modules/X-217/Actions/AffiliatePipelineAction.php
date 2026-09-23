<?php

declare(strict_types=1);

namespace App\Modules\X217\Actions;

use App\Modules\X205\Actions\AffiliateCreateAction;
use App\Modules\X217\Events\AffiliateDeclined;
use App\Modules\X217\Events\AffiliateRecruited;
use App\Modules\X217\Models\AffiliateProspect;
use App\Modules\X217\Models\RecruitmentOffer;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

final class AffiliatePipelineAction
{
    /**
     * Handles acceptance or decline of offer.
     * TEST ANCHOR: An accepted recruit lands in X-205 with the EXACT terms that were offered.
     */
    public function acceptOffer(int $businessId, int $offerId): string
    {
        $offer = RecruitmentOffer::where('business_id', $businessId)->findOrFail($offerId);
        $prospect = AffiliateProspect::where('business_id', $businessId)->findOrFail($offer->prospect_id);

        $offer->update([
            'is_accepted' => true,
            'accepted_at' => now(),
        ]);

        $prospect->update(['stage' => 'accepted']);

        $affiliateCode = 'PARTNER-'.strtoupper(Str::random(6));

        // TEST ANCHOR: Lands in X-205 with EXACT terms offered (commission_rate_bps == offered_rate_bps)
        app(AffiliateCreateAction::class)->create(
            $businessId,
            $affiliateCode,
            $prospect->partner_name,
            $offer->offered_rate_bps // EXACT TERMS (TEST ANCHOR)
        );

        Event::dispatch(new AffiliateRecruited($businessId, $prospect->id, $affiliateCode, $offer->offered_rate_bps));

        return $affiliateCode;
    }

    public function declineOffer(int $businessId, int $prospectId, string $reason = 'Terms rejected'): void
    {
        $prospect = AffiliateProspect::where('business_id', $businessId)->findOrFail($prospectId);
        $prospect->update(['stage' => 'declined']);

        Event::dispatch(new AffiliateDeclined($businessId, $prospect->id, $reason));
    }
}
