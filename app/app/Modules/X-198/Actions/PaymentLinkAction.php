<?php

declare(strict_types=1);

namespace App\Modules\X198\Actions;

use App\Models\Business;
use App\Modules\X198\Domain\StripeGatewayClient;
use App\Modules\X198\Models\Payment;
use App\Modules\X198\Models\PaymentLink;

final class PaymentLinkAction
{
    public function handle(int $businessId, int $paymentId): PaymentLink
    {
        $payment = Payment::where('business_id', $businessId)->findOrFail($paymentId);

        $existing = PaymentLink::where('business_id', $businessId)
            ->where('payment_id', $paymentId)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        // The customer reads this on the gateway's own Checkout page, so it must say who is being
        // paid. It was a constant naming no business and using this app's word for the event
        // (R233). Naming the invoice waits on a payments-to-invoice link, which does not exist.
        $business = Business::findOrFail($businessId);
        $description = $business->name.' - card payment';

        $client = app(StripeGatewayClient::class);
        $result = $client->createPaymentLink(
            $payment->amount_cents,
            $description,
            $payment->currency,
            'x198-paylink-'.$businessId.'-'.$paymentId
        );

        // The unique (business_id, payment_id) pair is the idempotency (R036). Two presses of the
        // same button both clear the pre-check above and both call the gateway; the loser must hand
        // back the winner's row rather than die on the index.
        return PaymentLink::firstOrCreate(
            [
                'business_id' => $businessId,
                'payment_id' => $paymentId,
            ],
            [
                'provider_link_id' => $result['id'],
                'url' => $result['url'],
            ]
        );
    }
}
