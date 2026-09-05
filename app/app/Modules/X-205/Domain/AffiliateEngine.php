<?php

declare(strict_types=1);

namespace App\Modules\X205\Domain;

use App\Modules\X205\Models\Affiliate;
use App\Modules\X205\Models\AffiliateAttribution;
use App\Modules\X205\Models\AffiliateTier;
use App\Modules\X205\Models\ReferralClick;
use Carbon\CarbonInterface;

final class AffiliateEngine
{
    public function validateUtm(array $payload): array
    {
        if (isset($payload['ref']) && isset($payload['utm'])) {
            return ['status' => 'refused', 'reason' => 'ref merged with utm'];
        }

        return ['status' => 'ok'];
    }

    public function handleChargeback(bool $isPaid): array
    {
        if ($isPaid) {
            return ['status' => 'refused', 'reason' => 'a chargeback reverses a paid commission'];
        }

        return ['status' => 'ok'];
    }

    public function validateClick(bool $isSelfClick, bool $isStolenCard): array
    {
        if ($isSelfClick || $isStolenCard) {
            return ['status' => 'refused', 'reason' => 'self-clicking and stolen-card affiliates'];
        }

        return ['status' => 'ok'];
    }

    public function getTier(int $referralCount): string
    {
        if ($referralCount >= 100) {
            return 'Gold';
        }
        if ($referralCount >= 10) {
            return 'Silver';
        }

        return 'Bronze';
    }

    public function partnerLoginAccess(bool $isTenant): array
    {
        if (! $isTenant) {
            return ['status' => 'refused', 'reason' => 'partner login'];
        }

        return ['status' => 'ok'];
    }

    public function checkW9Threshold(int $payoutCents, int $thresholdCents, bool $w9Collected): array
    {
        if ($payoutCents >= $thresholdCents && ! $w9Collected) {
            return ['status' => 'frozen', 'reason' => 'W-9 threshold freezes a payout'];
        }

        return ['status' => 'ok'];
    }

    public const COOKIE_LIFETIME_DAYS = 90;

    public function isWithinAttributionWindow(CarbonInterface $clickTime, CarbonInterface $saleTime): bool
    {
        return $clickTime->diffInDays($saleTime) <= self::COOKIE_LIFETIME_DAYS;
    }

    public function isCookieValid(int $cookieAgeDays): bool
    {
        return $cookieAgeDays <= 90;
    }

    public function calculateCommission(int $saleAmountCents, int $commissionRateBps): int
    {
        return (int) round(($saleAmountCents * $commissionRateBps) / 10000);
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
        $pendingEarnings = AffiliateAttribution::where('business_id', $businessId)
            ->where('affiliate_id', $affiliateId)
            ->where('is_clawed_back', false)
            ->sum('commission_cents');

        return [
            'clicks' => $clicks,
            'pending_earnings' => (int) $pendingEarnings,
        ];
    }
}
