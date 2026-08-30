<?php

declare(strict_types=1);

namespace App\Modules\X117\Actions;

use App\Modules\X117\Domain\CheckoutEngine;

final class CartCheckoutAction
{
    public function __construct(private readonly CheckoutEngine $engine) {}

    public function handle(
        int $businessId,
        int $sellableId,
        int $quantity,
        string $freshAuthToken,
        ?int $customerId = null
    ): array {
        return $this->engine->checkout($businessId, $sellableId, $quantity, $freshAuthToken, $customerId);
    }
}
