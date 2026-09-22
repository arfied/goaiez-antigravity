<?php

declare(strict_types=1);

namespace App\Modules\X210\Actions;

use App\Modules\X210\Events\PromotionCreated;
use App\Modules\X210\Models\Promotion;
use App\Modules\X210\Models\PromotionScope;
use DateTimeInterface;
use Illuminate\Support\Facades\Event;

final class PromotionCreateAction
{
    public function createPromotion(
        int $businessId,
        string $code,
        int $discountValue,
        string $discountType = 'percentage',
        ?int $maxRedemptions = null,
        int $velocityThreshold = 10,
        ?DateTimeInterface $expiresAt = null,
        ?int $measurementWindowDays = null,
        array $scopes = []
    ): Promotion {
        if ($measurementWindowDays === null || $measurementWindowDays <= 0) {
            throw new \InvalidArgumentException('A promotion with no measurement window cannot be created.');
        }

        if ($maxRedemptions === null || $maxRedemptions <= 0) {
            throw new \InvalidArgumentException('A promotion with no cap cannot be saved.');
        }

        $promo = Promotion::create([
            'business_id' => $businessId,
            'code' => strtoupper($code),
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'max_redemptions' => $maxRedemptions,
            'redemptions_count' => 0,
            'velocity_threshold_per_hour' => $velocityThreshold,
            'is_active' => true,
            'expires_at' => $expiresAt,
        ]);

        foreach ($scopes as $scope) {
            PromotionScope::create([
                'business_id' => $businessId,
                'promotion_id' => $promo->id,
                'scope_type' => $scope['scope_type'] ?? 'service_category',
                'scope_value' => $scope['scope_value'] ?? 'all',
            ]);
        }

        Event::dispatch(new PromotionCreated($businessId, $promo->id, $promo->code));

        return $promo;
    }
}
