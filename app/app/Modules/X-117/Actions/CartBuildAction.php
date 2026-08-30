<?php

declare(strict_types=1);

namespace App\Modules\X117\Actions;

use App\Modules\X117\Domain\CheckoutEngine;
use App\Modules\X117\Models\Cart;

final class CartBuildAction
{
    public function __construct(private readonly CheckoutEngine $engine) {}

    public function handle(int $businessId, string $sessionToken, array $items, int $expiresMinutes = 15): Cart
    {
        return $this->engine->buildCart($businessId, $sessionToken, $items, $expiresMinutes);
    }
}
