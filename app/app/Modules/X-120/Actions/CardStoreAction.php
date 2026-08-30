<?php

declare(strict_types=1);

namespace App\Modules\X120\Actions;

use App\Modules\X120\Events\CardStored;
use App\Modules\X120\Models\CardToken;
use Illuminate\Support\Facades\Event;

final class CardStoreAction
{
    public function store(
        int $businessId,
        string $gatewayCustomerId,
        string $gatewayPaymentMethodId,
        string $brand,
        string $lastFour,
        int $expMonth,
        int $expYear,
        bool $isDefault = true
    ): CardToken {
        if ($isDefault) {
            CardToken::where('business_id', $businessId)->update(['is_default' => false]);
        }

        $token = CardToken::create([
            'business_id' => $businessId,
            'gateway_customer_id' => $gatewayCustomerId,
            'gateway_payment_method_id' => $gatewayPaymentMethodId,
            'brand' => $brand,
            'last_four' => $lastFour,
            'exp_month' => $expMonth,
            'exp_year' => $expYear,
            'is_default' => $isDefault,
            'alert_sent' => false,
        ]);

        Event::dispatch(new CardStored($businessId, $token->id, $gatewayPaymentMethodId));

        return $token;
    }
}
