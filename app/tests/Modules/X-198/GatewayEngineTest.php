<?php

use App\Models\Business;
use App\Modules\X198\Domain\GatewayEngine;

test('capture persists real id', function () {
    $secret = config('credentials.stripe_secret');
    if (empty($secret)) {
        $this->markTestIncomplete('UNRESOLVED: journey J9 — payment provider test-mode keys absent from app/.env (reads stripe_secret)');
    }

    $business = Business::factory()->create();

    $engine = app(GatewayEngine::class);
    $engine->connect($business->id, 'stripe', 'acct_test');

    $payment = $engine->capture($business->id, 2500, 'tok_visa', 'idem_'.uniqid());

    expect($payment->gateway_charge_id)->not->toBeNull();
    expect($payment->gateway_charge_id)->toStartWith('ch_');
    expect($payment->status)->toBe('pending');
});
