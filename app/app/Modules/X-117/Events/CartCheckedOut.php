<?php

declare(strict_types=1);

namespace App\Modules\X117\Events;

final class CartCheckedOut
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $orderId,
        public readonly string $orderNumber,
        public readonly int $totalCents,
        public readonly string $authToken,
        public readonly ?int $customerId = null,
    ) {}
}
