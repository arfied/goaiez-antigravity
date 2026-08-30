<?php

declare(strict_types=1);

namespace App\Modules\X167\Events;

final class PoSent
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $purchaseOrderId,
        public readonly string $poNumber
    ) {}
}
