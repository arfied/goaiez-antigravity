<?php

declare(strict_types=1);

namespace App\Modules\X114\Events;

final class MediaGenerated
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $assetId,
        public readonly string $slotName,
        public readonly string $url
    ) {}
}
