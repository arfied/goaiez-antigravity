<?php

declare(strict_types=1);

namespace App\Modules\X117\Events;

final class InventoryUpdated
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $sellableId,
        public readonly int $newQuantity
    ) {}
}
