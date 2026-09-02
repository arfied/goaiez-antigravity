<?php

test('capture persists real id', function () {
    $path = storage_path('app/evidence/j9/charge.json');
    if (! file_exists($path)) {
        $this->fail('Artifact missing. You must run php artisan x198:evidence-charge first.');
    }

    $artifact = json_decode(file_get_contents($path), true);

    expect($artifact['gateway_charge_id'])->not->toBeNull();
    expect($artifact['gateway_charge_id'])->toStartWith('ch_');
    expect($artifact['payment_status'])->toBe('pending');
});
