<?php

declare(strict_types=1);

namespace App\Modules\X198\Actions;

use Illuminate\Support\Str;

final class PaymentLinkAction
{
    public function handle(int $businessId, int $amountCents, string $description): array
    {
        $linkToken = Str::random(24);

        return [
            'payment_url' => "https://pay.goaiez.com/link/{$linkToken}",
            'link_token' => $linkToken,
            'amount_cents' => $amountCents,
            'description' => $description,
        ];
    }
}
