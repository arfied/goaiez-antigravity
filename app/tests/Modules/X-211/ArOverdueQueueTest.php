<?php

declare(strict_types=1);

namespace Tests\Modules\X211;

use App\Models\Business;
use App\Modules\X199\Models\Invoice;
use App\Modules\X211\Events\ArOverdue;
use App\Modules\X211\Models\ArDunningAction;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class ArOverdueQueueTest extends TestCase
{
    public function test_ar_overdue_event_queues_and_processes_asynchronously(): void
    {
        $business = Business::factory()->create();
        $invoiceId = null;

        Tenancy::actingAs((int) $business->id, function () use ($business, &$invoiceId) {
            $invoice = Invoice::create([
                'business_id' => $business->id,
                'invoice_number' => 'INV-Q-1',
                'status' => 'issued',
                'due_date' => now()->subDays(10),
            ]);
            $invoiceId = (int) $invoice->id;

            config(['queue.default' => 'database']);

            Event::dispatch(new ArOverdue((int) $business->id, (int) $invoice->id, 10));

            $this->assertCount(0, ArDunningAction::where('business_id', $business->id)->get(), 'Action should not exist yet before draining queue.');
        });

        Tenancy::forgetAll();
        $this->assertNull(Tenancy::id());

        $this->artisan('queue:work --stop-when-empty');

        Tenancy::actingAs((int) $business->id, function () use ($business) {
            $actions = ArDunningAction::where('business_id', $business->id)->get();
            $this->assertCount(1, $actions);
            $this->assertEquals('escalate_to_human', $actions->first()->action);
            $this->assertEquals('R211: Overdue invoice requires human resolution attempt before any suspension.', $actions->first()->reason);
        });
    }


    public function test_listener_is_idempotent_when_processing_duplicate_events(): void
    {
        $business = Business::factory()->create();
        $invoiceId = null;

        Tenancy::actingAs((int) $business->id, function () use ($business, &$invoiceId) {
            $invoice = Invoice::create([
                'business_id' => $business->id,
                'invoice_number' => 'INV-DUP-1',
                'status' => 'issued',
                'due_date' => now()->subDays(10),
            ]);
            $invoiceId = (int) $invoice->id;

            config(['queue.default' => 'database']);

            // Dispatch twice
            Event::dispatch(new ArOverdue((int) $business->id, (int) $invoice->id, 10));
            Event::dispatch(new ArOverdue((int) $business->id, (int) $invoice->id, 10));
        });

        Tenancy::forgetAll();
        
        $this->artisan('queue:work --stop-when-empty');

        Tenancy::actingAs((int) $business->id, function () use ($business) {
            $actions = ArDunningAction::where('business_id', $business->id)->get();
            $this->assertCount(1, $actions, 'Should only create one action even if dispatched twice');
        });
    }
}
