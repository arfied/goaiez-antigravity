<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X120\Models\CardToken;

class X120Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-120';
    }

    public function fill(Business $business): int
    {
        if (CardToken::where('business_id', $business->id)->where('gateway_customer_id', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        CardToken::create([
            'business_id' => $business->id,
            'gateway_payment_method_id' => self::MARKER.'pm_4242',
            'gateway_customer_id' => self::MARKER.'cus_4242',
            'brand' => 'visa',
            'last_four' => '4242',
            'exp_month' => 12,
            'exp_year' => now()->year + 3,
            'is_default' => true,
            'alert_sent' => false,
        ]);

        CardToken::create([
            'business_id' => $business->id,
            'gateway_payment_method_id' => self::MARKER.'pm_5100',
            'gateway_customer_id' => self::MARKER.'cus_5100',
            'brand' => 'mastercard',
            'last_four' => '5100',
            'exp_month' => now()->subMonthNoOverflow()->month,
            'exp_year' => now()->subMonthNoOverflow()->year,
            'is_default' => false,
            'alert_sent' => false,
        ]);

        return 2;
    }

    public function purge(Business $business): int
    {
        return CardToken::where('business_id', $business->id)->where('gateway_customer_id', 'like', self::MARKER.'%')->delete();
    }
}
