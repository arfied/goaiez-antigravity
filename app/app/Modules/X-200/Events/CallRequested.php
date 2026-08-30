<?php

declare(strict_types=1);

namespace App\Modules\X200\Events;

final class CallRequested
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $campaignId,
        public readonly int $seatId,
        public readonly string $phone
    ) {}
}
