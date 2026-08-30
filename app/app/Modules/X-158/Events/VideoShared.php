<?php

declare(strict_types=1);

namespace App\Modules\X158\Events;

final class VideoShared
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $videoId,
        public readonly string $channel
    ) {}
}
