<?php

use App\Models\Business;
use App\Models\User;
use App\Modules\X199\Events\InvoiceDue;
use App\Modules\X199\Models\Invoice;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Artisan;

use App\Modules\X121\Models\Person;

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
            'pdf_url' => 'https://cdn.goaiez.com/invoices/inv.pdf',
        ]);

        $notDueInvoice = Invoice::create([
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-NOTDUE',
            'total_cents' => 1000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->addDays(5)->toDateString(),
            'pdf_url' => 'https://cdn.goaiez.com/invoices/inv.pdf',
        ]);

        // First run
        Artisan::call('x199:mark-due');

        Event::assertDispatched(InvoiceDue::class, function ($e) use ($overdueInvoice) {
            return $e->invoiceId === $overdueInvoice->id;
        });

        // Second run
        Artisan::call('x199:mark-due');

        // It should have only been dispatched ONCE total (due to idempotency marker)
        Event::assertDispatchedTimes(InvoiceDue::class, 1);
    });
});
