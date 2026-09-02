<?php

use App\Modules\X199\Domain\InvoiceEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;


test('it issues invoice in integer minor units', function () {
    $business = \App\Models\Business::factory()->create();
    $customer = \App\Modules\X121\Models\Person::create(['business_id' => $business->id]);
    
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
