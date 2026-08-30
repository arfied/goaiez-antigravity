<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Events;

final class ReplyPublished
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $replyId,
        public readonly string $platform
    ) {}
}
