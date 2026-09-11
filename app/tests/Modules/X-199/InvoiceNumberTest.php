<?php

use App\Models\Business;
use App\Modules\X121\Models\Person;
use App\Modules\X199\Domain\InvoiceEngine;
use App\Modules\X199\Domain\InvoiceNumber;
use App\Modules\X199\Models\Invoice;
use App\Support\Tenancy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('two invoices issued for the same business get consecutive numbers, and the second is greater than the first', function () {
    $business = Business::factory()->create();
    $customer = Person::create(['business_id' => $business->id]);

    $engine = app(InvoiceEngine::class);

    Tenancy::actingAs((int) $business->id, function () use ($engine, $business, $customer, &$inv1, &$inv2) {
        $first = $engine->issueInvoice(
            $business->id,
            $customer->id,
            [['description' => 'First', 'quantity' => 1, 'unit_price_cents' => 100]],
            'net_30'
        );

        $second = $engine->issueInvoice(
            $business->id,
            $customer->id,
            [['description' => 'Second', 'quantity' => 1, 'unit_price_cents' => 100]],
            'net_30'
        );

        $inv1 = $first['invoice']->invoice_number;
        $inv2 = $second['invoice']->invoice_number;
    });

    expect($inv1)->toBe('INV-000001');
    expect($inv2)->toBe('INV-000002');
    expect($inv2 > $inv1)->toBeTrue();
});

test('two businesses number independently', function () {
    $businessA = Business::factory()->create();
    $customerA = Person::create(['business_id' => $businessA->id]);

    $businessB = Business::factory()->create();
    $customerB = Person::create(['business_id' => $businessB->id]);

    $engine = app(InvoiceEngine::class);

    Tenancy::actingAs((int) $businessA->id, function () use ($engine, $businessA, $customerA) {
        $engine->issueInvoice(
            $businessA->id,
            $customerA->id,
            [['description' => 'A1', 'quantity' => 1, 'unit_price_cents' => 100]],
            'net_30'
        );
        $engine->issueInvoice(
            $businessA->id,
            $customerA->id,
            [['description' => 'A2', 'quantity' => 1, 'unit_price_cents' => 100]],
            'net_30'
        );
        $engine->issueInvoice(
            $businessA->id,
            $customerA->id,
            [['description' => 'A3', 'quantity' => 1, 'unit_price_cents' => 100]],
            'net_30'
        );
    });

    Tenancy::actingAs((int) $businessB->id, function () use ($engine, $businessB, $customerB, &$firstB) {
        $firstB = $engine->issueInvoice(
            $businessB->id,
            $customerB->id,
            [['description' => 'B1', 'quantity' => 1, 'unit_price_cents' => 100]],
            'net_30'
        );
    });

    expect($firstB['invoice']->invoice_number)->toBe('INV-000001');
});
test('a legacy invoice does not reset the counter', function () {
    $business = Business::factory()->create();
    $customer = Person::create(['business_id' => $business->id]);

    $engine = app(InvoiceEngine::class);

    Tenancy::actingAs((int) $business->id, function () use ($engine, $business, $customer, &$inv3) {
        $engine->issueInvoice(
            $business->id,
            $customer->id,
            [['description' => 'First', 'quantity' => 1, 'unit_price_cents' => 100]],
            'net_30'
        );

        $engine->issueInvoice(
            $business->id,
            $customer->id,
            [['description' => 'Second', 'quantity' => 1, 'unit_price_cents' => 100]],
            'net_30'
        );

        // Insert legacy directly
        Invoice::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-LEGACY',
            'status' => 'issued',
            'due_date' => now()->addDays(30),
            'total_cents' => 100,
        ]);

        $third = $engine->issueInvoice(
            $business->id,
            $customer->id,
            [['description' => 'Third', 'quantity' => 1, 'unit_price_cents' => 100]],
            'net_30'
        );

        $inv3 = $third['invoice']->invoice_number;
    });

    expect($inv3)->toBe('INV-000003');
});

test('the same invoice number cannot be written twice for one business', function () {
    $business = Business::factory()->create();
    $customer = Person::create(['business_id' => $business->id]);

    $engine = app(InvoiceEngine::class);

    Tenancy::actingAs((int) $business->id, function () use ($engine, $business, $customer) {
        $engine->issueInvoice(
            $business->id,
            $customer->id,
            [['description' => 'First', 'quantity' => 1, 'unit_price_cents' => 100]],
            'net_30'
        );

        expect(function () use ($business, $customer) {
            DB::transaction(function () use ($business, $customer) {
                DB::table('invoices')->insert([
                    'business_id' => $business->id,
                    'customer_id' => $customer->id,
                    'invoice_number' => 'INV-000001',
                    'status' => 'issued',
                    'due_date' => now()->addDays(30),
                    'total_cents' => 100,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        })->toThrow(QueryException::class, 'invoices_business_id_invoice_number_unique');
    });
});

test('two businesses may each hold INV-000001', function () {
    $businessA = Business::factory()->create();
    $customerA = Person::create(['business_id' => $businessA->id]);

    $businessB = Business::factory()->create();
    $customerB = Person::create(['business_id' => $businessB->id]);

    $engine = app(InvoiceEngine::class);

    Tenancy::actingAs((int) $businessA->id, function () use ($engine, $businessA, $customerA, &$invA) {
        $first = $engine->issueInvoice(
            $businessA->id,
            $customerA->id,
            [['description' => 'A1', 'quantity' => 1, 'unit_price_cents' => 100]],
            'net_30'
        );
        $invA = $first['invoice']->invoice_number;
    });

    Tenancy::actingAs((int) $businessB->id, function () use ($engine, $businessB, $customerB, &$invB) {
        $first = $engine->issueInvoice(
            $businessB->id,
            $customerB->id,
            [['description' => 'B1', 'quantity' => 1, 'unit_price_cents' => 100]],
            'net_30'
        );
        $invB = $first['invoice']->invoice_number;
    });

    expect($invA)->toBe('INV-000001');
    expect($invB)->toBe('INV-000001');
});

test('the first invoice for a business takes a lock even though there is no row to lock', function () {
    $business = Business::factory()->create();

    Tenancy::actingAs((int) $business->id, function () use ($business) {
        DB::transaction(function () use ($business) {
            InvoiceNumber::next((int) $business->id);

            $held = DB::selectOne(
                "select count(*) as n from pg_locks where locktype = 'advisory' and classid = 199 and objid = ?",
                [$business->id]
            );
            expect((int) $held->n)->toBe(1);
        });
    });
});
