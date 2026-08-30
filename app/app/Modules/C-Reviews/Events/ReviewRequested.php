<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Events;

final class ReviewRequested
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $reviewRequestId,
        public readonly string $platform
    ) {}
}
