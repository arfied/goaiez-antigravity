<?php

declare(strict_types=1);

namespace App\Modules\X117\Actions;

use App\Modules\X117\Domain\CheckoutEngine;

final class OrderCancelAction
{
    public function __construct(private readonly CheckoutEngine $engine) {}

    public function handle(int $businessId, int $orderId): array
    {
        return $this->engine->cancelOrder($businessId, $orderId);
    }
}
