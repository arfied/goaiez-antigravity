<?php

declare(strict_types=1);

namespace App\Modules\X185\Events;

final class CampaignSent
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $sequenceId,
        public readonly int $stepNumber,
        public readonly string $channel
    ) {}
}
