<?php

declare(strict_types=1);

namespace App\Modules\X198\Actions;

use App\Modules\X198\Domain\StripeGatewayClient;
use App\Modules\X198\Models\Payment;
use App\Modules\X198\Models\PaymentLink;

final class PaymentLinkAction
{
    public function handle(int $businessId, int $paymentId, string $description): PaymentLink
    {
        $payment = Payment::where('business_id', $businessId)->findOrFail($paymentId);

        $existing = PaymentLink::where('business_id', $businessId)
            ->where('payment_id', $paymentId)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $client = app(StripeGatewayClient::class);
        $result = $client->createPaymentLink($payment->amount_cents, $description);

        return PaymentLink::create([
            'business_id' => $businessId,
            'payment_id' => $paymentId,
            'provider_link_id' => $result['id'],
            'url' => $result['url'],
        ]);
    }
}
