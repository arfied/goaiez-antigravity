<?php

declare(strict_types=1);

namespace App\Services\Zernio;

final readonly class FacebookReviewPage
{
    /**
     * @param  list<FacebookReview>  $reviews
     */
    public function __construct(
        public array $reviews,
        public ?string $nextCursor,
    ) {}
}
