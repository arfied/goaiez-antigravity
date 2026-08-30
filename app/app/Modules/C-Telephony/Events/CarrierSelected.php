<?php

declare(strict_types=1);

namespace App\Modules\CTelephony\Events;

final class CarrierSelected
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $carrierName,
        public readonly string $threadKey
    ) {}
}
