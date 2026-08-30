<?php

declare(strict_types=1);

namespace App\Modules\X218\Events;

final class InfluencerEngaged
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $dealId,
        public readonly int $influencerId
    ) {}
}
