<?php

declare(strict_types=1);

namespace App\Modules\X167\Events;

final class ReorderTriggered
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $stockItemId,
        public readonly float $currentQuantity
    ) {}
}
