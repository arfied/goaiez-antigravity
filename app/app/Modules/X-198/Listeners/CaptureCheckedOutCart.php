<?php

declare(strict_types=1);

namespace App\Modules\X198\Listeners;

use App\Modules\X117\Events\CartCheckedOut;
use App\Modules\X198\Models\MerchantConnection;

/**
 * This listener makes no gateway call and leaves the order at pending_payment.
 * It still has a job the day a real token arrives from a connected card-entry surface.
 * That token comes from the card-entry surface, never from this event: the event carries no
 * payment instrument and never did carry a real one (citing rulings 45 and 148).
 */
final class CaptureCheckedOutCart
{
    public function handle(CartCheckedOut $event): void
    {
        if (! MerchantConnection::where('business_id', $event->businessId)->where('is_connected', true)->exists()) {
            return;
        }

        // We make no gateway call here because there is no payment instrument to send yet.
        // The token we have is only a nonce, not a Stripe-issued source token.
    }
}
