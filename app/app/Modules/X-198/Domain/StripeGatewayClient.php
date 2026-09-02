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
            throw new RuntimeException('Missing stripe_secret');
        }

        $response = Http::withToken($secret)
            ->asForm()
            ->post('https://api.stripe.com/v1/charges', [
                'amount' => $amountCents,
                'currency' => strtolower($currency),
                'source' => $source,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Stripe charge failed: ' . $response->body());
        }

        $id = $response->json('id');
        if (!is_string($id)) {
            throw new RuntimeException('Invalid response from Stripe: ' . $response->body());
        }

        return $id;
    }
}
