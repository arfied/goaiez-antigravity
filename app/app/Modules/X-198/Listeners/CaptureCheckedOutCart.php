<?php

declare(strict_types=1);

namespace App\Modules\X198\Listeners;

use App\Modules\X117\Events\CartCheckedOut;
use App\Modules\X117\Models\Order;
use App\Modules\X198\Domain\GatewayEngine;
use App\Modules\X198\Models\MerchantConnection;

final class CaptureCheckedOutCart
{
    public function handle(CartCheckedOut $event): void
    {
        if (! MerchantConnection::where('business_id', $event->businessId)->where('is_connected', true)->exists()) {
            return;
        }

        $payment = app(GatewayEngine::class)->capture(
            $event->businessId,
            $event->totalCents,
            $event->authToken,
            'x117-order-'.$event->orderId
        );

        if ($payment->gateway_charge_id !== null) {
            Order::whereKey($event->orderId)->update(['status' => 'paid']);
        }
    }
}
