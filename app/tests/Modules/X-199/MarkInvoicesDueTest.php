<?php

use App\Models\Business;
use App\Models\User;
use App\Modules\X121\Models\Person;
use App\Modules\X199\Events\InvoiceDue;
use App\Modules\X199\Models\Invoice;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;

test('it marks invoices due and dispatches event only once', function () {
    Event::fake();

    $user = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $user->id]);
    $customer = Person::create(['business_id' => $business->id]);

    Tenancy::actingAs((int) $business->id, function () use ($business, $customer) {
        $overdueInvoice = Invoice::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-OVERDUE',
            'total_cents' => 1000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

        $notDueInvoice = Invoice::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-NOTDUE',
            'total_cents' => 1000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        // First run
        Artisan::call('x199:mark-due');

        Event::assertDispatched(InvoiceDue::class, function ($e) use ($overdueInvoice) {
            return $e->invoiceId === $overdueInvoice->id;
        });

        // Second run
        Artisan::call('x199:mark-due');

        // Once for THIS invoice — the idempotency marker, not a fact about the whole database.
        expect(Event::dispatched(InvoiceDue::class, fn ($e) => $e->invoiceId === $overdueInvoice->id))->toHaveCount(1);
        expect(Event::dispatched(InvoiceDue::class, fn ($e) => $e->invoiceId === $notDueInvoice->id))->toHaveCount(0);
    });
});
