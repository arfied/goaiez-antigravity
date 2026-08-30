<?php

declare(strict_types=1);

namespace App\Modules\X163\Events;

final class PricebookUpdated
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $itemId,
        public readonly string $serviceName,
        public readonly int $priceCents
    ) {}
}
