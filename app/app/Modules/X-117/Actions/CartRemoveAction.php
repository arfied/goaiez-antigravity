<?php

declare(strict_types=1);

namespace App\Modules\X117\Actions;

use App\Modules\X117\Domain\CheckoutEngine;
use App\Modules\X117\Models\Cart;

final class CartRemoveAction
{
    public function __construct(private readonly CheckoutEngine $engine = new CheckoutEngine) {}

    public function handle(int $businessId, string $sessionToken, int $sellableId): Cart
    {
        return $this->engine->removeFromCart($businessId, $sessionToken, $sellableId);
    }
}
