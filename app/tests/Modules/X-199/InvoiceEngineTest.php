<?php

use App\Models\Business;
use App\Modules\X121\Models\Person;
use App\Modules\X199\Domain\InvoiceEngine;

test('it issues invoice in integer minor units', function () {
    $business = Business::factory()->create();
    $customer = Person::create(['business_id' => $business->id]);

    $engine = app(InvoiceEngine::class);
    $result = $engine->issueInvoice(
        $business->id,
        $customer->id,
        [['description' => 'Test Service', 'quantity' => 1, 'unit_price_cents' => 12500]],
        'net_30'
    );

    $invoice = $result['invoice'];
    expect($invoice->total_cents)->toBe(12500);
    expect($invoice->status)->toBe('issued');
});
