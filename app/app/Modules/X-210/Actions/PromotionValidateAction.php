<?php

declare(strict_types=1);

namespace App\Modules\X210\Actions;

use App\Modules\X210\Events\PromotionCapReached;
use App\Modules\X210\Events\PromotionExpired;
use App\Modules\X210\Models\Promotion;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

final class PromotionValidateAction
{
    public function validatePromotion(int $businessId, string $code): Promotion
    {
        $promo = Promotion::where('business_id', $businessId)
            ->where('code', strtoupper($code))
            ->firstOrFail();

        if (! $promo->is_active) {
            throw new InvalidArgumentException('Promotion is inactive');
        }

        if ($promo->expires_at && $promo->expires_at->isPast()) {
            Event::dispatch(new PromotionExpired($businessId, $promo->id, $promo->code));
            throw new InvalidArgumentException('Promotion has expired');
        }

        if ($promo->redemptions_count >= $promo->max_redemptions) {
            Event::dispatch(new PromotionCapReached($businessId, $promo->id, $promo->redemptions_count));
            throw new InvalidArgumentException('Promotion maximum redemptions cap reached');
        }

        return $promo;
    }
}
