<?php

declare(strict_types=1);

namespace App\Modules\X109\Events;

final class QuotaExhausted
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $campaignId
    ) {}
}
