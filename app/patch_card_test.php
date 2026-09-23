<?php

$content = file_get_contents('tests/Feature/Billing/CardExpirySettingsTest.php');
$search = "CardToken::create([
        'business_id' => \$this->biz->id,
        'card_token' => 'tok_123',
        'last_four' => '4242',
        'brand' => 'visa',
        'exp_month' => \$expDate->month,
        'exp_year' => \$expDate->year,
        'alert_sent' => false,
    ]);";
$replace = "\App\Modules\X120\Models\CardToken::create([
        'business_id' => \$this->biz->id,
        'gateway_customer_id' => 'cus_123',
        'gateway_payment_method_id' => 'tok_123',
        'brand' => 'visa',
        'last_four' => '4242',
        'exp_month' => \$expDate->month,
        'exp_year' => \$expDate->year,
        'alert_sent' => false
    ]);";
$content = str_replace($search, $replace, $content);
file_put_contents('tests/Feature/Billing/CardExpirySettingsTest.php', $content);
