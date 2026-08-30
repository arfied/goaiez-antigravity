<?php

declare(strict_types=1);

namespace App\Modules\X217\Actions;

use App\Modules\X217\Domain\RecruitmentGuard;
use App\Modules\X217\Models\AffiliateProspect;
use App\Modules\X217\Models\RecruitmentOffer;

final class AffiliateTermsOfferAction
{
    private RecruitmentGuard $guard;

    public function __construct(?RecruitmentGuard $guard = null)
    {
        $this->guard = $guard ?? new RecruitmentGuard;
    }

    /**
     * Composes and sends terms offer.
     * TEST ANCHOR: No offer exceeds the confirmed ceiling.
     */
    public function makeOffer(
        int $businessId,
        int $prospectId,
        int $offeredRateBps,
        int $ceilingRateBps = RecruitmentGuard::DEFAULT_MAX_CEILING_BPS,
        string $termsSummary = 'Standard 90-day cookie with monthly payouts'
    ): RecruitmentOffer {
        $prospect = AffiliateProspect::where('business_id', $businessId)->findOrFail($prospectId);

        // TEST ANCHOR: No offer exceeds the confirmed ceiling
        $this->guard->assertWithinCeiling($offeredRateBps, $ceilingRateBps);

        $prospect->update(['stage' => 'negotiating']);

        return RecruitmentOffer::create([
            'business_id' => $businessId,
            'prospect_id' => $prospect->id,
            'offered_rate_bps' => $offeredRateBps,
            'ceiling_rate_bps' => $ceilingRateBps,
            'terms_summary' => $termsSummary,
            'is_accepted' => false,
        ]);
    }
}
