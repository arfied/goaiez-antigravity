<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ReviewIngested
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly int $businessId,
        public readonly int $reviewId,
        public readonly string $platform,
        public readonly ?int $rating,
        public readonly int $locationId,
    ) {}
}
