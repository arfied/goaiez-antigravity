<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X205\Models\Affiliate;
use App\Modules\X205\Models\AffiliateAttribution;
use App\Modules\X205\Models\AffiliatePayout;

class X205Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-205';
    }

    public function fill(Business $business): int
    {
        if (Affiliate::where('business_id', $business->id)->where('partner_name', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $affiliate1 = Affiliate::create([
            'business_id' => $business->id,
            'affiliate_code' => self::MARKER.'a1',
            'partner_name' => self::MARKER.'Alpha Partners',
            'commission_rate_bps' => 1000,
            'lifetime_earnings_cents' => 10000,
            'current_balance_cents' => 5000,
        ]);

        $affiliate2 = Affiliate::create([
            'business_id' => $business->id,
            'affiliate_code' => self::MARKER.'a2',
            'partner_name' => self::MARKER.'Beta Affiliates',
            'commission_rate_bps' => 1500,
            'lifetime_earnings_cents' => 20000,
            'current_balance_cents' => 10000,
        ]);

        AffiliateAttribution::create([
            'business_id' => $business->id,
            'affiliate_id' => $affiliate1->id,
            'order_id' => self::MARKER.'ord1',
            'sale_amount_cents' => 100000,
            'commission_cents' => 10000,
            'attributed_at' => now()->subDays(2),
        ]);

        AffiliateAttribution::create([
            'business_id' => $business->id,
            'affiliate_id' => $affiliate2->id,
            'order_id' => self::MARKER.'ord2',
            'sale_amount_cents' => 133333,
            'commission_cents' => 20000,
            'attributed_at' => now()->subDay(),
        ]);

        AffiliatePayout::create([
            'business_id' => $business->id,
            'affiliate_id' => $affiliate1->id,
            'amount_cents' => 5000,
            'status' => 'requested',
            'money_moved' => false,
        ]);

        return 5;
    }

    public function purge(Business $business): int
    {
        $count = AffiliatePayout::where('business_id', $business->id)
            ->whereHas('affiliate', function ($q) {
                $q->where('partner_name', 'like', self::MARKER.'%');
            })->delete();

        $count += AffiliateAttribution::where('business_id', $business->id)
            ->whereHas('affiliate', function ($q) {
                $q->where('partner_name', 'like', self::MARKER.'%');
            })->delete();

        $count += Affiliate::where('business_id', $business->id)
            ->where('partner_name', 'like', self::MARKER.'%')
            ->delete();

        return $count;
    }
}
