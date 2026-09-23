<?php

declare(strict_types=1);

namespace App\Modules\X205\Actions;

use App\Modules\X205\Models\Affiliate;

final class AffiliateCreateAction
{
    public function create(int $businessId, string $affiliateCode, string $partnerName, int $commissionRateBps): void
    {
        Affiliate::create([
            'business_id' => $businessId,
            'affiliate_code' => $affiliateCode,
            'partner_name' => $partnerName,
            'commission_rate_bps' => $commissionRateBps,
            'lifetime_earnings_cents' => 0,
            'current_balance_cents' => 0,
        ]);
    }
}
