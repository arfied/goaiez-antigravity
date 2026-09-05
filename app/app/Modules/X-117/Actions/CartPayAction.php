<?php

declare(strict_types=1);

namespace App\Modules\X117\Actions;

use App\Modules\X117\Domain\CheckoutEngine;

final class CartPayAction
{
    public function __construct(private readonly CheckoutEngine $engine) {}

    public function handle(
        int $businessId,
        string $sessionToken,
        string $freshAuthToken,
        ?int $customerId = null
    ): array {
        return $this->engine->checkoutCart($businessId, $sessionToken, $freshAuthToken, $customerId);
    }
}
