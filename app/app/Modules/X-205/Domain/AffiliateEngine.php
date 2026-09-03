<?php

declare(strict_types=1);

namespace App\Modules\X205\Domain;

use App\Modules\X205\Models\Affiliate;
use App\Modules\X205\Models\AffiliateTier;
use App\Modules\X205\Models\ReferralClick;
use Carbon\CarbonInterface;

final class AffiliateEngine
{
    public const COOKIE_LIFETIME_DAYS = 90; // G13-20: 90-day cookie window

    public function isWithinAttributionWindow(CarbonInterface $clickTime, CarbonInterface $saleTime): bool
    {
        return $clickTime->diffInDays($saleTime) <= self::COOKIE_LIFETIME_DAYS;
    }

    public function calculateCommission(int $saleAmountCents, int $rateBps): int
    {
        return (int) round(($saleAmountCents * $rateBps) / 10000);
    }

    public function getCommissionRateForReferralCount(int $businessId, int $referralCount, int $baseRateBps): int
    {
        $tier = AffiliateTier::where('business_id', $businessId)
            ->where('min_referrals', '<=', $referralCount)
            ->orderBy('min_referrals', 'desc')
            ->first();

        return $tier ? $tier->commission_rate_bps : $baseRateBps;
    }

    public function recordClick(int $businessId, string $visitorId, ?string $refCode, ?string $utmSource = null, ?string $utmMedium = null, ?string $utmCampaign = null): ReferralClick
    {
        $affiliateId = null;
        if ($refCode) {
            $affiliate = Affiliate::where('business_id', $businessId)->where('affiliate_code', $refCode)->first();
            if ($affiliate) {
                $affiliateId = $affiliate->id;
            }
        }

        $click = ReferralClick::where('business_id', $businessId)->where('visitor_id', $visitorId)->first();
        if ($click) {
            // merge: utm never overwrites an existing ref
            if (! $click->affiliate_id && $affiliateId) {
                $click->affiliate_id = $affiliateId;
            }
            if (! $click->utm_source && $utmSource) {
                $click->utm_source = $utmSource;
            }
            if (! $click->utm_medium && $utmMedium) {
                $click->utm_medium = $utmMedium;
            }
            if (! $click->utm_campaign && $utmCampaign) {
                $click->utm_campaign = $utmCampaign;
            }
            $click->save();

            return $click;
        }

        return ReferralClick::create([
            'business_id' => $businessId,
            'affiliate_id' => $affiliateId,
            'visitor_id' => $visitorId,
            'utm_source' => $utmSource,
            'utm_medium' => $utmMedium,
            'utm_campaign' => $utmCampaign,
        ]);
    }

    public function getPartnerData(int $businessId, int $affiliateId): array
    {
        $clicks = ReferralClick::where('business_id', $businessId)->where('affiliate_id', $affiliateId)->get();
        $affiliate = Affiliate::where('business_id', $businessId)->where('id', $affiliateId)->first();
        return [
            'clicks' => $clicks,
            'pending_earnings' => $affiliate ? $affiliate->current_balance_cents : 0,
        ];
    }
}
