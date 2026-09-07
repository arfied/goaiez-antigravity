<?php

use App\Models\Business;
use App\Modules\X121\Models\Person;
use App\Modules\X199\Domain\InvoiceEngine;
use App\Modules\X199\Domain\InvoiceReader;
use App\Modules\X211\Domain\ArEngine;
use App\Modules\X211\Domain\UnreferencedPaymentException;
use App\Modules\X211\Events\ArOverdue;
use App\Modules\X211\Listeners\ProcessOverdueReceivable;
use App\Modules\X211\Models\ArDunningAction;
use App\Modules\X211\Models\OfflinePayment;

test('dunning sequence escalates before suspend', function () {
    $business = Business::factory()->create();
    $customer = Person::create(['business_id' => $business->id]);

    $engine = app(InvoiceEngine::class);
    $result = $engine->issueInvoice(
        $business->id,
        $customer->id,
        [['description' => 'Test', 'quantity' => 1, 'unit_price_cents' => 40000]],
        'net_30'
    );
    $invoice = $result['invoice'];

    $event = new ArOverdue($business->id, $invoice->id, 10);
    $listener = new ProcessOverdueReceivable;
    $listener->handle($event);

    $action = ArDunningAction::where('invoice_id', $invoice->id)->latest('id')->first();

    expect($action)->not->toBeNull();
    expect($action->action)->toBe('escalate_to_human');
    expect($action->reason)->toContain('nothing is stopped until someone does');
});

/**
 * [G1-74]
 */
test('payment with no reference and no photo is refused', function () {
    $business = Business::factory()->create();
    $customer = Person::create(['business_id' => $business->id]);

    $engine = app(InvoiceEngine::class);
    $result = $engine->issueInvoice(
        $business->id,
        $customer->id,
        [['description' => 'Test', 'quantity' => 1, 'unit_price_cents' => 40000]],
        'net_30'
    );
    $invoice = $result['invoice'];

    $arEngine = app(ArEngine::class);

    $thrown = false;
    try {
        $arEngine->logOfflinePayment($business->id, $invoice->id, 10000, 'check', null, null);
    } catch (UnreferencedPaymentException $e) {
        $thrown = true;
        expect($e->getMessage())->toContain($invoice->invoice_number);
    }

    expect($thrown)->toBeTrue();
    expect(OfflinePayment::where('business_id', $business->id)->where('invoice_id', $invoice->id)->count())->toBe(0);

    $freshInvoice = app(InvoiceReader::class)->forBusiness($business->id, $invoice->id);
    expect($freshInvoice->paid_cents)->toBe(0);
    expect($freshInvoice->status)->toBe('issued');
});

test('payment with whitespace reference is refused', function () {
    $business = Business::factory()->create();
    $customer = Person::create(['business_id' => $business->id]);

    $engine = app(InvoiceEngine::class);
    $result = $engine->issueInvoice(
        $business->id,
        $customer->id,
        [['description' => 'Test', 'quantity' => 1, 'unit_price_cents' => 40000]],
        'net_30'
    );
    $invoice = $result['invoice'];

    $arEngine = app(ArEngine::class);

    $thrown = false;
    try {
        $arEngine->logOfflinePayment($business->id, $invoice->id, 10000, 'check', '   ', null);
    } catch (UnreferencedPaymentException $e) {
        $thrown = true;
        expect($e->getMessage())->toContain($invoice->invoice_number);
    }

    expect($thrown)->toBeTrue();
    expect(OfflinePayment::where('business_id', $business->id)->where('invoice_id', $invoice->id)->count())->toBe(0);

    $freshInvoice = app(InvoiceReader::class)->forBusiness($business->id, $invoice->id);
    expect($freshInvoice->paid_cents)->toBe(0);
    expect($freshInvoice->status)->toBe('issued');
});

test('payment with a photo path and no reference is accepted', function () {
    $business = Business::factory()->create();
    $customer = Person::create(['business_id' => $business->id]);

    $engine = app(InvoiceEngine::class);
    $result = $engine->issueInvoice(
        $business->id,
        $customer->id,
        [['description' => 'Test', 'quantity' => 1, 'unit_price_cents' => 40000]],
        'net_30'
    );
    $invoice = $result['invoice'];

    $arEngine = app(ArEngine::class);
    $payment = $arEngine->logOfflinePayment($business->id, $invoice->id, 10000, 'check', null, 'photos/receipt.jpg');

    expect($payment->photo_path)->toBe('photos/receipt.jpg');
    expect(OfflinePayment::where('business_id', $business->id)->where('invoice_id', $invoice->id)->count())->toBe(1);

    $freshInvoice = app(InvoiceReader::class)->forBusiness($business->id, $invoice->id);
    expect($freshInvoice->paid_cents)->toBe(10000);
});

test('existing accepted path still works', function () {
    $business = Business::factory()->create();
    $customer = Person::create(['business_id' => $business->id]);

    $engine = app(InvoiceEngine::class);
    $result = $engine->issueInvoice(
        $business->id,
        $customer->id,
        [['description' => 'Test', 'quantity' => 1, 'unit_price_cents' => 40000]],
        'net_30'
    );
    $invoice = $result['invoice'];

    $arEngine = app(ArEngine::class);
    $payment = $arEngine->logOfflinePayment($business->id, $invoice->id, 10000, 'check', 'REF123');

    expect($payment->reference_number)->toBe('REF123');
    expect(OfflinePayment::where('business_id', $business->id)->where('invoice_id', $invoice->id)->count())->toBe(1);

    $freshInvoice = app(InvoiceReader::class)->forBusiness($business->id, $invoice->id);
    expect($freshInvoice->paid_cents)->toBe(10000);
});
