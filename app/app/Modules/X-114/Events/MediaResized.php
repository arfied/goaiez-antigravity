<?php

declare(strict_types=1);

namespace App\Modules\X114\Events;

final class MediaResized
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $assetId,
        public readonly int $width,
        public readonly int $height
    ) {}
}
