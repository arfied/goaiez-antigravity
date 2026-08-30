<?php

declare(strict_types=1);

namespace App\Modules\CTelephony\Events;

final class CarrierFailedOver
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $fromCarrier,
        public readonly string $toCarrier,
        public readonly string $reason
    ) {}
}
