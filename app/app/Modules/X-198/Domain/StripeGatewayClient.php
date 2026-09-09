<?php

declare(strict_types=1);

namespace App\Modules\X198\Domain;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class StripeGatewayClient
{
    /**
     * ⭐ $idempotencyKey is sent to the gateway as its own `Idempotency-Key` header, so a repeated
     * request returns the FIRST charge instead of making a second one. It must already be
     * namespaced by business: every charge here posts with the PLATFORM secret and no
     * `Stripe-Account` (R093), so all tenants share one idempotency namespace at the provider.
     */
    public function charge(int $amountCents, string $source, string $currency, string $idempotencyKey): string
    {
        $secret = config('credentials.stripe_secret');
        if (empty($secret)) {
            throw new GatewayNotConfiguredException('No payment gateway credential is configured in this checkout, so nothing was sent to the gateway.');
        }

        $response = Http::withToken($secret)
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->asForm()
            ->post('https://api.stripe.com/v1/charges', [
                'amount' => $amountCents,
                'currency' => strtolower($currency),
                'source' => $source,
            ]);

        if ($response->failed()) {
            throw new RuntimeException($this->refusal('The gateway refused the charge', $response));
        }

        $id = $response->json('id');
        if (! is_string($id)) {
            throw new RuntimeException('The gateway accepted the charge request but sent back no charge id, so the charge could not be confirmed.');
        }

        return $id;
    }

    /**
     * ⭐ $idempotencyKey is sent as the gateway's own `Idempotency-Key` header, so a second press
     * returns the FIRST checkout session instead of opening a second one nobody can reach. It must
     * already be namespaced by business, for the reason charge() gives above (R093).
     */
    public function createPaymentLink(int $amountCents, string $description, string $currency, string $idempotencyKey): array
    {
        $secret = config('credentials.stripe_secret');
        if (empty($secret)) {
            throw new GatewayNotConfiguredException('No payment gateway credential is configured in this checkout, so nothing was sent to the gateway.');
        }

        $response = Http::withToken($secret)
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->asForm()
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => strtolower($currency),
                            'unit_amount' => $amountCents,
                            'product_data' => [
                                'name' => $description,
                            ],
                        ],
                        'quantity' => 1,
                    ],
                ],
                'success_url' => config('app.url'),
            ]);

        if ($response->failed()) {
            throw new RuntimeException($this->refusal('The gateway would not open a payment page', $response));
        }

        $id = $response->json('id');
        $url = $response->json('url');

        if (! is_string($id) || ! is_string($url)) {
            throw new RuntimeException('The gateway accepted the request but sent back no payment page, so no link was made.');
        }

        return ['id' => $id, 'url' => $url];
    }

    /**
     * The gateway's own sentence where it sent one, and its status where it did not.
     * ⛔ Never the raw response body: an owner reads this string on the declines screen.
     */
    private function refusal(string $act, Response $response): string
    {
        $message = $response->json('error.message');

        if (is_string($message) && $message !== '') {
            return $act.': '.$message;
        }

        return $act.': the gateway answered '.$response->status().' and gave no reason.';
    }
}
