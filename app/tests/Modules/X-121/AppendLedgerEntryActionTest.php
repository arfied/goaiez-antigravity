<?php

declare(strict_types=1);

namespace Tests\Modules\X121;

use App\Models\Business;
use App\Modules\X121\Actions\AppendLedgerEntryAction;
use App\Modules\X121\Models\LedgerEntry;
use App\Support\Tenancy;
use Illuminate\Database\QueryException;
use Tests\TestCase;

final class AppendLedgerEntryActionTest extends TestCase
{
    public function test_writes_exact_fields_and_returns_created_model(): void
    {
        $business = Business::factory()->create();
        Tenancy::set($business->id);

        $action = new AppendLedgerEntryAction;

        $entry = $action->handle(
            $business->id,
            'ai_call_debit',
            -150,
            'GBP',
            9850,
            'Test description'
        );

        $this->assertInstanceOf(LedgerEntry::class, $entry);

        $this->assertDatabaseHas('ledger_entries', [
            'id' => $entry->id,
            'business_id' => $business->id,
            'entry_type' => 'ai_call_debit',
            'amount_cents' => -150,
            'currency' => 'GBP',
            'balance_after_cents' => 9850,
            'description' => 'Test description',
        ]);

        $this->assertSame(-150, $entry->amount_cents);
    }

    public function test_cross_tenant_write_is_refused_by_rls(): void
    {
        $bizA = TestCase::provisionTenant(['name' => 'Biz A', 'currency' => 'USD']);
        $bizB = TestCase::provisionTenant(['name' => 'Biz B', 'currency' => 'USD']);

        Tenancy::set($bizA->id);

        $action = new AppendLedgerEntryAction;

        $caught = false;
        try {
            $action->handle(
                $bizB->id,
                'ai_call_debit',
                -150,
                'GBP',
                9850,
                'Test description'
            );
        } catch (QueryException $e) {
            $caught = true;
        }

        $this->assertTrue($caught, 'Expected QueryException due to RLS was not thrown.');
        $this->assertDatabaseCount('ledger_entries', 0);
    }
}
