<?php

declare(strict_types=1);

namespace App\Modules\X182\Events;

final class PostPublished
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $postId,
        public readonly string $platform
    ) {}
}
