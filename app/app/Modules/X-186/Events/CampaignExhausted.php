<?php

declare(strict_types=1);

namespace App\Modules\X186\Events;

final class CampaignExhausted
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $campaignId,
        public readonly int $personId
    ) {}
}
