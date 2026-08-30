<?php

declare(strict_types=1);

namespace App\Modules\X186\Events;

final class SequenceStopped
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $campaignId,
        public readonly int $personId,
        public readonly string $reason
    ) {}
}
