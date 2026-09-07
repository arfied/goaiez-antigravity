<?php

use App\Models\Business;
use App\Modules\X121\Models\Person;
use App\Modules\X198\Domain\GatewayEngine;
use App\Modules\X198\Domain\StripeGatewayClient;
use App\Modules\X198\Models\MerchantConnection;
use App\Modules\X198\Models\Payment;
use App\Modules\X199\Domain\InvoiceEngine;
use App\Modules\X199\Domain\InvoiceReader;
use App\Modules\X199\Events\InvoicePaid;
use App\Modules\X199\Events\LimitExceeded;
use App\Modules\X199\Events\OverflowCharged;
use App\Modules\X199\Events\OverflowReversed;
use App\Modules\X199\Models\CreditTerm;
use App\Modules\X199\Models\OverflowCharge;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

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

test('the overflow rule — card absorbs limit overflow and payment reverses it', function () {
    Event::fake([LimitExceeded::class, OverflowCharged::class, OverflowReversed::class]);

    $business = Business::factory()->create();
    $customer = Person::create(['business_id' => $business->id]);

    Tenancy::actingAs((int) $business->id, function () use ($business, $customer) {
        MerchantConnection::create([
            'business_id' => $business->id,
            'gateway_name' => 'stripe',
            'merchant_account_id' => 'acct_test',
            'is_connected' => true,
        ]);

        // Setup terms with $5,000 limit, currently at $4,000 outstanding
        $terms = CreditTerm::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'terms_type' => 'net_30',
            'credit_limit_cents' => 500000,
            'current_outstanding_cents' => 400000,
            'card_on_file_token' => 'tok_visa',
        ]);

        // Fake the gateway at HTTP client because StripeGatewayClient and GatewayEngine are marked final
        Http::fake([
            'api.stripe.com/*' => Http::response(['id' => 'ch_mock_123'], 200),
        ]);

        $engine = app(InvoiceEngine::class);

        // Issue $1,500 invoice -> outstanding becomes $5,500, overflow is $500.
        $result = $engine->issueInvoice(
            $business->id,
            $customer->id,
            [['description' => 'Big Service', 'quantity' => 1, 'unit_price_cents' => 150000]],
            'net_30'
        );

        $invoice = $result['invoice'];

        expect($result['is_over_limit'])->toBeTrue();

        Event::assertDispatched(LimitExceeded::class, function ($e) use ($business) {
            return $e->businessId === $business->id && $e->outstandingCents === 550000;
        });

        Event::assertDispatched(OverflowCharged::class, function ($e) use ($invoice) {
            return $e->invoiceId === $invoice->id && $e->amountCents === 50000;
        });

        $charge = OverflowCharge::where('invoice_id', $invoice->id)->where('charge_type', 'overflow_charged')->first();
        expect($charge)->not->toBeNull();
        expect($charge->amount_cents)->toBe(50000);
        expect($charge->reference_id)->toBe('ch_mock_123');

        // Pay the invoice -> reverses the charge for the same amount
        $engine->recordPayment($business->id, $invoice->id);

        $reversals = OverflowCharge::where('invoice_id', $invoice->id)->where('charge_type', 'overflow_reversed')->get();
        expect($reversals)->toHaveCount(1);

        $reversal = $reversals->first();
        expect($reversal->amount_cents)->toBe(50000);
        expect($reversal->amount_cents)->toBe($charge->amount_cents); // Assert it's the exact same amount

        Event::assertDispatched(OverflowReversed::class, function ($e) use ($invoice) {
            return $e->invoiceId === $invoice->id && $e->amountCents === 50000;
        });
    });
});

test('a reversal the gateway never performed carries no reference id', function () {
    $business = Business::factory()->create();
    $customer = Person::create(['business_id' => $business->id]);

    Tenancy::actingAs((int) $business->id, function () use ($business, $customer) {
        MerchantConnection::create([
            'business_id' => $business->id,
            'gateway_name' => 'stripe',
            'merchant_account_id' => 'acct_test',
            'is_connected' => true,
        ]);

        $terms = CreditTerm::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'terms_type' => 'net_30',
            'credit_limit_cents' => 500000,
            'current_outstanding_cents' => 400000,
            'card_on_file_token' => 'tok_visa',
        ]);

        Http::fake([
            'api.stripe.com/*' => Http::response(['id' => 'ch_mock_123'], 200),
        ]);

        $engine = app(InvoiceEngine::class);

        $result = $engine->issueInvoice(
            $business->id,
            $customer->id,
            [['description' => 'Big Service', 'quantity' => 1, 'unit_price_cents' => 150000]],
            'net_30'
        );

        $invoice = $result['invoice'];
        $charge = OverflowCharge::where('invoice_id', $invoice->id)->where('charge_type', 'overflow_charged')->first();
        expect($charge->reference_id)->toBe('ch_mock_123');

        $engine->recordPayment($business->id, $invoice->id);

        $reversal = OverflowCharge::where('invoice_id', $invoice->id)->where('charge_type', 'overflow_reversed')->first();
        expect($reversal->reference_id)->toBeNull();
    });
});

test('no installments are allowed by schema', function () {
    // Assert by grepping an existing directory for 'installment'
    $process = new Process(['grep', '-ri', 'installment', base_path('app/Modules/X-199/')]);
    $process->run();
    $output = $process->getOutput();

    // It should be empty (no output)
    expect(trim($output))->toBe('');

    // Also assert the module directory actually exists so the grep is honest
    expect(is_dir(base_path('app/Modules/X-199/')))->toBeTrue();
});

test('the no-gateway case', function () {
    Event::fake([LimitExceeded::class, OverflowCharged::class, OverflowReversed::class]);

    $business = Business::factory()->create();
    $customer = Person::create(['business_id' => $business->id]);

    Tenancy::actingAs((int) $business->id, function () use ($business, $customer) {
        $terms = CreditTerm::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'terms_type' => 'net_30',
            'credit_limit_cents' => 500000,
            'current_outstanding_cents' => 400000,
            'card_on_file_token' => 'tok_visa',
        ]);

        $engine = app(InvoiceEngine::class);
        $result = $engine->issueInvoice(
            $business->id,
            $customer->id,
            [['description' => 'Big Service', 'quantity' => 1, 'unit_price_cents' => 150000]],
            'net_30'
        );

        expect($result['invoice']->status)->toBe('issued');
        expect($result['is_over_limit'])->toBeTrue();

        Event::assertDispatched(LimitExceeded::class);
        Event::assertNotDispatched(OverflowCharged::class);

        $charge = OverflowCharge::where('invoice_id', $result['invoice']->id)->where('charge_type', 'overflow_charged')->first();
        expect($charge)->not->toBeNull();
        expect($charge->status)->toBe('refused');
    });
});

test('the no-card case', function () {
    Event::fake([LimitExceeded::class, OverflowCharged::class, OverflowReversed::class]);

    $business = Business::factory()->create();
    $customer = Person::create(['business_id' => $business->id]);

    Tenancy::actingAs((int) $business->id, function () use ($business, $customer) {
        MerchantConnection::create([
            'business_id' => $business->id,
            'gateway_name' => 'stripe',
            'merchant_account_id' => 'acct_test',
            'is_connected' => true,
        ]);

        $terms = CreditTerm::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'terms_type' => 'net_30',
            'credit_limit_cents' => 500000,
            'current_outstanding_cents' => 400000,
            'card_on_file_token' => null,
        ]);

        $engine = app(InvoiceEngine::class);
        $result = $engine->issueInvoice(
            $business->id,
            $customer->id,
            [['description' => 'Big Service', 'quantity' => 1, 'unit_price_cents' => 150000]],
            'net_30'
        );

        expect($result['invoice']->status)->toBe('issued');
        expect($result['is_over_limit'])->toBeTrue();

        Event::assertDispatched(LimitExceeded::class);
        Event::assertNotDispatched(OverflowCharged::class);

        $charge = OverflowCharge::where('invoice_id', $result['invoice']->id)->where('charge_type', 'overflow_charged')->first();
        expect($charge)->not->toBeNull();
        expect($charge->status)->toBe('refused');

        $engine->recordPayment($business->id, $result['invoice']->id);

        $reversals = OverflowCharge::where('invoice_id', $result['invoice']->id)->where('charge_type', 'overflow_reversed')->get();
        expect($reversals)->toHaveCount(0);

        Event::assertNotDispatched(OverflowReversed::class);
    });
});

test('the idempotency case: one Payment for two attempts at the same charge', function () {
    $business = Business::factory()->create();

    Tenancy::actingAs((int) $business->id, function () use ($business) {
        MerchantConnection::create([
            'business_id' => $business->id,
            'gateway_name' => 'stripe',
            'merchant_account_id' => 'acct_test',
            'is_connected' => true,
        ]);

        Http::fake([
            'api.stripe.com/*' => Http::response(['id' => 'ch_mock_123'], 200),
        ]);

        $gatewayEngine = app(GatewayEngine::class);
        $idempotencyKey = 'overflow_99_50000';

        $payment1 = $gatewayEngine->capture(
            businessId: $business->id,
            amountCents: 50000,
            paymentToken: 'tok_visa',
            idempotencyKey: $idempotencyKey
        );

        $payment2 = $gatewayEngine->capture(
            businessId: $business->id,
            amountCents: 50000,
            paymentToken: 'tok_visa',
            idempotencyKey: $idempotencyKey
        );

        expect($payment1->id)->toBe($payment2->id);
        $count = Payment::where('business_id', $business->id)->count();
        expect($count)->toBe(1);
    });
});

test('an overflow on a non-Stripe connection is refused, not charged', function () {
    Event::fake([LimitExceeded::class, OverflowCharged::class, OverflowReversed::class]);

    $business = Business::factory()->create();
    $customer = Person::create(['business_id' => $business->id]);

    Tenancy::actingAs((int) $business->id, function () use ($business, $customer) {
        MerchantConnection::create([
            'business_id' => $business->id,
            'gateway_name' => 'square',
            'merchant_account_id' => 'acct_test',
            'is_connected' => true,
        ]);

        // Setup terms with $5,000 limit, currently at $4,000 outstanding
        $terms = CreditTerm::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'terms_type' => 'net_30',
            'credit_limit_cents' => 500000,
            'current_outstanding_cents' => 400000,
            'card_on_file_token' => 'tok_visa',
        ]);

        $engine = app(InvoiceEngine::class);

        // Issue $1,500 invoice -> outstanding becomes $5,500, overflow is $500.
        $result = $engine->issueInvoice(
            $business->id,
            $customer->id,
            [['description' => 'Big Service', 'quantity' => 1, 'unit_price_cents' => 150000]],
            'net_30'
        );

        $invoice = $result['invoice'];

        expect($result['is_over_limit'])->toBeTrue();

        $charge = OverflowCharge::where('invoice_id', $invoice->id)->where('charge_type', 'overflow_charged')->first();
        expect($charge)->not->toBeNull();
        expect($charge->status)->toBe('refused');
        expect($charge->reference_id)->toBeNull();

        Event::assertNotDispatched(OverflowCharged::class);

        $payment = Payment::where('business_id', $business->id)->first();
        expect($payment)->not->toBeNull();
        expect($payment->status)->toBe('awaiting_processor');
        expect($payment->gateway_charge_id)->toBeNull();
    });
});

test('a partial payment leaves the invoice unpaid, chased and unreversed', function () {
    Event::fake([LimitExceeded::class, OverflowCharged::class, OverflowReversed::class, InvoicePaid::class]);

    $business = Business::factory()->create();
    $customer = Person::create(['business_id' => $business->id]);

    Tenancy::actingAs((int) $business->id, function () use ($business, $customer) {
        MerchantConnection::create([
            'business_id' => $business->id,
            'gateway_name' => 'stripe',
            'merchant_account_id' => 'acct_test',
            'is_connected' => true,
        ]);

        // Setup terms with $5,000 limit, currently at $4,000 outstanding
        $terms = CreditTerm::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'terms_type' => 'net_30',
            'credit_limit_cents' => 500000,
            'current_outstanding_cents' => 400000,
            'card_on_file_token' => 'tok_visa',
        ]);

        // Fake the gateway at HTTP client because StripeGatewayClient and GatewayEngine are marked final
        Http::fake([
            'api.stripe.com/*' => Http::response(['id' => 'ch_mock_123'], 200),
        ]);

        $engine = app(InvoiceEngine::class);

        // Issue $1,500 invoice -> outstanding becomes $5,500, overflow is $500.
        $result = $engine->issueInvoice(
            $business->id,
            $customer->id,
            [['description' => 'Big Service', 'quantity' => 1, 'unit_price_cents' => 150000]],
            'net_30'
        );

        $invoice = $result['invoice'];

        expect($result['is_over_limit'])->toBeTrue();

        Event::assertDispatched(LimitExceeded::class, function ($e) use ($business) {
            return $e->businessId === $business->id && $e->outstandingCents === 550000;
        });

        Event::assertDispatched(OverflowCharged::class, function ($e) use ($invoice) {
            return $e->invoiceId === $invoice->id && $e->amountCents === 50000;
        });

        $charge = OverflowCharge::where('invoice_id', $invoice->id)->where('charge_type', 'overflow_charged')->first();
        expect($charge)->not->toBeNull();
        expect($charge->amount_cents)->toBe(50000);
        expect($charge->reference_id)->toBe('ch_mock_123');

        $res = $engine->recordPayment($business->id, $invoice->id, 20000);

        $invoice->refresh();

        expect($res['status'])->not->toBe('paid');
        expect($invoice->paid_cents)->toBe(20000);
        expect($invoice->status)->not->toBe('paid');
        expect($invoice->paid_at)->toBeNull();

        expect(OverflowCharge::where('invoice_id', $invoice->id)
            ->where('charge_type', 'overflow_reversed')
            ->count())->toBe(0);

        Event::assertNotDispatched(OverflowReversed::class);
        Event::assertNotDispatched(InvoicePaid::class);

        $openIds = app(InvoiceReader::class)
            ->openUnpaidForBusiness($business->id)
            ->pluck('id')
            ->all();
        expect($openIds)->toContain($invoice->id);
    });
});
