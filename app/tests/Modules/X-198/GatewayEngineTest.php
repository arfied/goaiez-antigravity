<?php

test('capture persists real id', function () {
    $path = storage_path('app/evidence/j9/charge.json');
    if (! file_exists($path)) {
        $this->fail('Artifact missing. You must run php artisan x198:evidence-charge first.');
    }

    $artifact = json_decode(file_get_contents($path), true);

    expect($artifact['gateway_charge_id'])->not->toBeNull();
    expect($artifact['gateway_charge_id'])->toStartWith('ch_');
    expect(strlen($artifact['gateway_charge_id']))->toBe(27);
    expect($artifact['payment_status'])->toBe('captured');
});

test('pay link returns real url', function () {
    $path = storage_path('app/evidence/x198/payment-link.json');
    if (! file_exists($path)) {
        $this->fail('Artifact missing. You must run php artisan x198:evidence-payment-link first.');
    }

    $artifact = json_decode(file_get_contents($path), true);

    expect($artifact['provider_link_id'])->toStartWith('cs_');
    expect($artifact['url'])->toStartWith('https://checkout.stripe.com');
    expect(strlen($artifact['url']))->toBeGreaterThan(400);
    expect($artifact['running_unit_tests'])->toBeFalse();
});
