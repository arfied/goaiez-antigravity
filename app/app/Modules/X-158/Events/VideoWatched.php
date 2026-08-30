<?php

declare(strict_types=1);

namespace App\Modules\X158\Events;

final class VideoWatched
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $videoId,
        public readonly string $viewerSessionId,
        public readonly float $watchDepthPercent
    ) {}
}
