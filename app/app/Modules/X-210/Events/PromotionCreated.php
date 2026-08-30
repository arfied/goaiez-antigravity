<?php

declare(strict_types=1);

namespace App\Modules\X210\Events;

final class PromotionCreated
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $promotionId,
        public readonly string $code
    ) {}
}
