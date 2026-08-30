<?php

declare(strict_types=1);

namespace App\Modules\X189\Events;

final class MediaBranded
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $mediaId,
        public readonly string $outputMediaUrl
    ) {}
}
