<?php

declare(strict_types=1);

namespace App\Modules\X198\Domain;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class StripeGatewayClient
{
    public function charge(int $amountCents, string $source, string $currency = 'USD'): string
    {
        $secret = config('credentials.stripe_secret');
        if (empty($secret)) {
            throw new GatewayNotConfiguredException('Missing stripe_secret');
        }

        $response = Http::withToken($secret)
            ->asForm()
            ->post('https://api.stripe.com/v1/charges', [
                'amount' => $amountCents,
                'currency' => strtolower($currency),
                'source' => $source,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Stripe charge failed: '.$response->body());
        }

        $id = $response->json('id');
        if (! is_string($id)) {
            throw new RuntimeException('Invalid response from Stripe: '.$response->body());
        }

        return $id;
    }

    public function createPaymentLink(int $amountCents, string $description, string $currency = 'USD'): array
    {
        $secret = config('credentials.stripe_secret');
        if (empty($secret)) {
            throw new GatewayNotConfiguredException('Missing stripe_secret');
        }

        $response = Http::withToken($secret)
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
            throw new RuntimeException('Stripe checkout session failed: '.$response->body());
        }

        $id = $response->json('id');
        $url = $response->json('url');

        if (! is_string($id) || ! is_string($url)) {
            throw new RuntimeException('Invalid response from Stripe: '.$response->body());
        }

        return ['id' => $id, 'url' => $url];
    }
}
