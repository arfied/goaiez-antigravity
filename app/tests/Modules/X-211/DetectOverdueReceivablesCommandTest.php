<?php

declare(strict_types=1);

namespace Tests\Modules\X211;

use App\Models\Business;
use App\Modules\X199\Models\Invoice;
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

        Tenancy::actingAs((int) $business->id, function () use ($business) {
            Invoice::create([
                'business_id' => $business->id,
                'invoice_number' => 'INV-DRAFT',
                'status' => 'draft',
                'due_date' => now()->subDays(10),
            ]);
            Invoice::create([
                'business_id' => $business->id,
                'invoice_number' => 'INV-ISSUED',
                'status' => 'issued',
                'due_date' => now()->subDays(10),
            ]);
        });
        Tenancy::forgetAll();

        Event::fake([ArOverdue::class]);

        Artisan::call('x211:detect-overdue');

        Event::assertDispatchedTimes(ArOverdue::class, 1);
    }
}
