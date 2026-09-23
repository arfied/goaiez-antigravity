<?php

declare(strict_types=1);

namespace App\Modules\X205\Actions;

use App\Modules\X205\Domain\AffiliateEngine;
use App\Modules\X205\Domain\SaleAttributionRefused;
use App\Modules\X205\Models\Affiliate;
use App\Modules\X205\Models\AffiliateAttribution;
use App\Modules\X205\Models\ReferralClick;

final class AffiliateAttributeAction
{
    private AffiliateEngine $engine;

    public function __construct(?AffiliateEngine $engine = null)
    {
        $this->engine = $engine ?? app(AffiliateEngine::class);
    }

    /**
     * Attributes sale to affiliate partner (G13-20).
     */
    public function attributeSale(
        int $businessId,
        string $affiliateCode,
        string $orderId,
        int $saleAmountCents,
        string $visitorId = '',
        array $orderTags = []
    ): AffiliateAttribution {
        $affiliate = Affiliate::where('business_id', $businessId)
            ->where('affiliate_code', $affiliateCode)
            ->firstOrFail();

        if ($visitorId !== '') {
            $click = ReferralClick::where('business_id', $businessId)
                ->where('visitor_id', $visitorId)
                ->where('affiliate_id', $affiliate->id)
                ->first();

            if (! $click || ! $this->engine->isWithinAttributionWindow($click->created_at, now())) {
                throw new SaleAttributionRefused('Sale outside 90-day cookie or missing click');
            }
        }

        // G7-41: Tiers
        // Calculate referral count (existing attributions)
        $referralCount = AffiliateAttribution::where('business_id', $businessId)
            ->where('affiliate_id', $affiliate->id)
            ->count();

        $rateBps = $this->engine->getCommissionRateForReferralCount($businessId, $referralCount, $affiliate->commission_rate_bps);
        $commission = $this->engine->calculateCommission($saleAmountCents, $rateBps);

        // G7-23: Fraud Detection
        $fraudReviewStatus = 'none';

        if (in_array('stolen_card', $orderTags, true)) {
            $fraudReviewStatus = 'proposed';
        }

        $attribution = AffiliateAttribution::create([
            'business_id' => $businessId,
            'affiliate_id' => $affiliate->id,
            'order_id' => $orderId,
            'sale_amount_cents' => $saleAmountCents,
            'commission_cents' => $commission,
            'attributed_at' => now(),
            'is_clawed_back' => false,
            'clawback_status' => 'none',
            'fraud_review_status' => $fraudReviewStatus,
        ]);

        $affiliate->increment('lifetime_earnings_cents', $commission);
        $affiliate->increment('current_balance_cents', $commission);

        return $attribution;
    }
}
