<?php

declare(strict_types=1);

namespace App\Modules\X205\Actions;

use App\Modules\X205\Domain\AffiliateEngine;
use App\Modules\X205\Models\Affiliate;
use App\Modules\X205\Models\AffiliateAttribution;

final class AffiliateAttributeAction
{
    private AffiliateEngine $engine;

    public function __construct(?AffiliateEngine $engine = null)
    {
        $this->engine = $engine ?? new AffiliateEngine;
    }

    /**
     * Attributes sale to affiliate partner (G13-20).
     */
    public function attributeSale(
        int $businessId,
        string $affiliateCode,
        string $orderId,
        int $saleAmountCents
    ): AffiliateAttribution {
        $affiliate = Affiliate::where('business_id', $businessId)
            ->where('affiliate_code', $affiliateCode)
            ->firstOrFail();

        $commission = $this->engine->calculateCommission($saleAmountCents, $affiliate->commission_rate_bps);

        $attribution = AffiliateAttribution::create([
            'business_id' => $businessId,
            'affiliate_id' => $affiliate->id,
            'order_id' => $orderId,
            'sale_amount_cents' => $saleAmountCents,
            'commission_cents' => $commission,
            'attributed_at' => now(),
            'is_clawed_back' => false,
            'clawback_status' => 'none',
        ]);

        $affiliate->increment('lifetime_earnings_cents', $commission);
        $affiliate->increment('current_balance_cents', $commission);

        return $attribution;
    }
}
