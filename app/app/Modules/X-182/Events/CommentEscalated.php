<?php

declare(strict_types=1);

namespace App\Modules\X182\Events;

final class CommentEscalated
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $commentId,
        public readonly string $reason
    ) {}
}
