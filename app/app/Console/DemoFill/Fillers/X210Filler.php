<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X210\Models\Promotion;
use App\Modules\X210\Models\PromotionRedemption;
use App\Modules\X210\Models\PromotionScope;

class X210Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-210';
    }

    public function fill(Business $business): int
    {
        if (Promotion::where('business_id', $business->id)->where('code', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $promo = Promotion::create([
            'business_id' => $business->id,
            'code' => self::MARKER.'DEMO',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'max_redemptions' => 10,
            'redemptions_count' => 1,
            'is_active' => true,
        ]);

        PromotionScope::create([
            'business_id' => $business->id,
            'promotion_id' => $promo->id,
            'scope_type' => 'customer_segment',
            'scope_value' => self::MARKER.'VIP',
        ]);

        PromotionRedemption::create([
            'business_id' => $business->id,
            'promotion_id' => $promo->id,
            'customer_id' => 1,
            'order_id' => self::MARKER.'ORD',
            'discount_applied_cents' => 1000,
            'redeemed_at' => now(),
        ]);

        return 3;
    }

    public function purge(Business $business): int
    {
        $count = 0;
        $promos = Promotion::where('business_id', $business->id)->where('code', 'like', self::MARKER.'%')->get();
        foreach ($promos as $promo) {
            $count += PromotionRedemption::where('promotion_id', $promo->id)->delete();
            $count += PromotionScope::where('promotion_id', $promo->id)->delete();
            $count += $promo->delete();
        }

        return $count;
    }
}
