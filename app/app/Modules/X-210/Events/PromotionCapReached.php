<?php

declare(strict_types=1);

namespace App\Modules\X210\Events;

final class PromotionCapReached
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $promotionId,
        public readonly int $totalRedemptions
    ) {}
}
