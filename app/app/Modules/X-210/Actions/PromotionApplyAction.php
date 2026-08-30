<?php

declare(strict_types=1);

namespace App\Modules\X210\Actions;

use App\Modules\X210\Events\PromotionCapReached;
use App\Modules\X210\Events\PromotionRedeemed;
use App\Modules\X210\Events\PromotionVelocityAlert;
use App\Modules\X210\Models\PromotionRedemption;
use Illuminate\Support\Facades\Event;

final class PromotionApplyAction
{
    private PromotionValidateAction $validateAction;

    public function __construct(?PromotionValidateAction $validateAction = null)
    {
        $this->validateAction = $validateAction ?? new PromotionValidateAction;
    }

    public function applyPromotion(
        int $businessId,
        string $code,
        int $customerId,
        string $orderId,
        int $orderAmountCents
    ): PromotionRedemption {
        $promo = $this->validateAction->validatePromotion($businessId, $code);

        if ($promo->discount_type === 'percentage') {
            $discountCents = (int) round(($orderAmountCents * $promo->discount_value) / 100);
        } else {
            $discountCents = min($orderAmountCents, $promo->discount_value);
        }

        $redemption = PromotionRedemption::create([
            'business_id' => $businessId,
            'promotion_id' => $promo->id,
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'discount_applied_cents' => $discountCents,
            'redeemed_at' => now(),
        ]);

        $promo->increment('redemptions_count');

        Event::dispatch(new PromotionRedeemed($businessId, $promo->id, $customerId, $discountCents));

        // Check redemption velocity in the last hour
        $recentCount = PromotionRedemption::where('business_id', $businessId)
            ->where('promotion_id', $promo->id)
            ->where('redeemed_at', '>=', now()->subHour())
            ->count();

        if ($recentCount >= $promo->velocity_threshold_per_hour) {
            Event::dispatch(new PromotionVelocityAlert($businessId, $promo->id, $recentCount));
        }

        if ($promo->redemptions_count >= $promo->max_redemptions) {
            $promo->update(['is_active' => false]);
            Event::dispatch(new PromotionCapReached($businessId, $promo->id, $promo->redemptions_count));
        }

        return $redemption;
    }
}
