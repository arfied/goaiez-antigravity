<?php

declare(strict_types=1);

namespace App\Modules\X177\Events;

final class GbpPosted
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $postId,
        public readonly string $zernioDispatchId
    ) {}
}
