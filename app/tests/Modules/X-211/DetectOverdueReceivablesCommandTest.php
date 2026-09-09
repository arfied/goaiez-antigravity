<?php

declare(strict_types=1);

namespace Tests\Modules\X211;

use App\Models\Business;
use App\Modules\X199\Domain\InvoiceReader;
use App\Modules\X199\Models\Invoice;
use App\Modules\X211\Domain\ArEngine;
use App\Modules\X211\Events\ArOverdue;
use App\Modules\X211\Models\ArDunningAction;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class DetectOverdueReceivablesCommandTest extends TestCase
{
    public function test_detect_overdue_computes_positive_age_days(): void
    {
        Tenancy::forgetAll();
        $business = Business::factory()->create();

        Tenancy::actingAs((int) $business->id, function () use ($business) {
            Invoice::create([
                'business_id' => $business->id,
                'invoice_number' => 'INV-TEST-AGE',
                'status' => 'issued',
                'due_date' => now()->subDays(10),
            ]);
        });
        Tenancy::forgetAll();

        Event::fake([ArOverdue::class]);

        Artisan::call('x211:detect-overdue');

        Event::assertDispatched(ArOverdue::class, function ($event) {
            return $event->ageDays === 10;
        });
    }

    public function test_command_scans_invoices_cross_tenant_without_acting_as(): void
    {
        Tenancy::forgetAll();

        $business = Business::factory()->create();

        Tenancy::actingAs((int) $business->id, function () use ($business) {
            Invoice::create([
                'business_id' => $business->id,
                'invoice_number' => 'INV-TEST-1',
                'status' => 'issued',
                'due_date' => now()->subDays(5),
            ]);
        });

        Tenancy::forgetAll();

        $this->assertNull(Tenancy::id());

        // Use sync queue so the listener processes immediately in this test
        config(['queue.default' => 'sync']);

        // Run the command once
        Artisan::call('x211:detect-overdue');

        Tenancy::actingAs((int) $business->id, function () use ($business) {
            $actions = ArDunningAction::where('business_id', $business->id)->get();
            $this->assertCount(1, $actions);
            $this->assertEquals('escalate_to_human', $actions->first()->action);
        });

        // Run the command twice to prove idempotence
        Artisan::call('x211:detect-overdue');

        Tenancy::actingAs((int) $business->id, function () use ($business) {
            $actions = ArDunningAction::where('business_id', $business->id)->get();
            $this->assertCount(1, $actions, 'Command should be idempotent and not create duplicate actions.');
        });
    }

    public function test_ignores_draft_and_chases_issued(): void
    {
        Tenancy::forgetAll();
        $business = Business::factory()->create();

        $draftId = null;
        $issuedId = null;

        Tenancy::actingAs((int) $business->id, function () use ($business, &$draftId, &$issuedId) {
            $draft = Invoice::create([
                'business_id' => $business->id,
                'invoice_number' => 'INV-DRAFT',
                'status' => 'draft',
                'due_date' => now()->subDays(10),
            ]);
            $draftId = $draft->id;

            $issued = Invoice::create([
                'business_id' => $business->id,
                'invoice_number' => 'INV-ISSUED',
                'status' => 'issued',
                'due_date' => now()->subDays(10),
            ]);
            $issuedId = $issued->id;
        });
        Tenancy::forgetAll();

        Event::fake([ArOverdue::class]);

        Artisan::call('x211:detect-overdue');

        Event::assertNotDispatched(ArOverdue::class, fn ($e) => $e->invoiceId === $draftId);
        Event::assertDispatched(ArOverdue::class, fn ($e) => $e->invoiceId === $issuedId);
    }

    /**
     * [N-037]
     */
    public function test_an_open_recover_blocks_dunning_entirely_n_037(): void
    {
        Tenancy::forgetAll();
        $business = Business::factory()->create();

        $disputedId = null;
        $complaintId = null;
        $silenceId = null;

        Tenancy::actingAs((int) $business->id, function () use ($business, &$disputedId, &$complaintId, &$silenceId) {
            $disputed = Invoice::create([
                'business_id' => $business->id,
                'invoice_number' => 'INV-DISP',
                'status' => 'issued',
                'due_date' => now()->subDays(10),
            ]);
            $disputedId = $disputed->id;

            $complaint = Invoice::create([
                'business_id' => $business->id,
                'invoice_number' => 'INV-COMP',
                'status' => 'issued',
                'due_date' => now()->subDays(10),
            ]);
            $complaintId = $complaint->id;

            $silence = Invoice::create([
                'business_id' => $business->id,
                'invoice_number' => 'INV-SIL',
                'status' => 'issued',
                'due_date' => now()->subDays(10),
            ]);
            $silenceId = $silence->id;

            $engine = app(ArEngine::class);
            $engine->recordReason($business->id, $disputedId, 'disputed_line');
            $engine->recordReason($business->id, $complaintId, 'complaint');
        });

        Tenancy::forgetAll();

        Tenancy::actingAs((int) $business->id, function () use ($disputedId, $complaintId, $silenceId) {
            $this->assertEquals(2, ArDunningAction::where('invoice_id', $disputedId)->count());
            $this->assertEquals(2, ArDunningAction::where('invoice_id', $complaintId)->count());
            $this->assertEquals(0, ArDunningAction::where('invoice_id', $silenceId)->count());
        });
        Tenancy::forgetAll();

        Event::fake([ArOverdue::class]);

        Artisan::call('x211:detect-overdue');

        Event::assertNotDispatched(ArOverdue::class, function ($e) use ($disputedId) {
            return $e->invoiceId === $disputedId;
        }); // No new dunning action should be written for a disputed line

        Event::assertNotDispatched(ArOverdue::class, function ($e) use ($complaintId) {
            return $e->invoiceId === $complaintId;
        }); // No new dunning action should be written for a complaint

        Event::assertDispatched(ArOverdue::class, function ($e) use ($silenceId) {
            return $e->invoiceId === $silenceId;
        }); // Silence invoice should be chased
    }

    public function test_the_overdue_reader_is_asked_for_one_business_and_returns_only_its_issued_overdue_invoices(): void
    {
        Tenancy::forgetAll();
        $business = Business::factory()->create();

        Tenancy::actingAs((int) $business->id, function () use ($business) {
            Invoice::create([
                'business_id' => $business->id,
                'invoice_number' => 'INV-READER-OVERDUE',
                'status' => 'issued',
                'due_date' => now()->subDays(7),
            ]);
            Invoice::create([
                'business_id' => $business->id,
                'invoice_number' => 'INV-READER-PAID',
                'status' => 'paid',
                'due_date' => now()->subDays(7),
            ]);
            Invoice::create([
                'business_id' => $business->id,
                'invoice_number' => 'INV-READER-FUTURE',
                'status' => 'issued',
                'due_date' => now()->addDays(7),
            ]);

            $numbers = app(InvoiceReader::class)
                ->overdueIssued((int) $business->id)
                ->pluck('invoice_number')
                ->all();

            $this->assertSame(['INV-READER-OVERDUE'], $numbers);
        });

        Tenancy::forgetAll();
    }

    public function test_the_second_sweep_says_every_overdue_invoice_is_already_with_a_human(): void
    {
        Tenancy::forgetAll();

        $business = Business::factory()->create();

        Tenancy::actingAs((int) $business->id, function () use ($business) {
            Invoice::create([
                'business_id' => $business->id,
                'invoice_number' => 'INV-PARKED-1',
                'status' => 'issued',
                'due_date' => now()->subDays(5),
            ]);
        });

        Tenancy::forgetAll();

        // Sync so the listener writes the escalate_to_human row inside the first sweep.
        config(['queue.default' => 'sync']);

        // The first sweep chases everything overdue, so the second finds nothing to dispatch.
        Artisan::call('x211:detect-overdue');

        Tenancy::actingAs((int) $business->id, function () use ($business) {
            $this->assertCount(1, ArDunningAction::where('business_id', $business->id)
                ->where('action', 'escalate_to_human')
                ->get());
        });

        Artisan::call('x211:detect-overdue');
        $output = Artisan::output();

        $this->assertStringContainsString('overdue invoice(s) found; every one is already with a human, so none was dispatched', $output);
        $this->assertStringNotContainsString('No overdue invoices found to chase', $output);
    }
}
