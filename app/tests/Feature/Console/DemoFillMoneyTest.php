<?php

namespace Tests\Feature\Console;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X199\Models\Invoice;
use App\Support\Tenancy;
use Tests\TestCase;

class DemoFillMoneyTest extends TestCase
{
    protected function tearDown(): void
    {
        Tenancy::forgetAll();
        parent::tearDown();
    }

    public function test_money_fillers_write_marked_rows_once(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'C-Billing,X-104,X-198,X-199,X-211,X-120,X-173,X-202,X-156'])->assertExitCode(0);

        Tenancy::set($biz->id);

        $this->assertDatabaseHas('invoices', ['business_id' => $biz->id, 'invoice_number' => 'demo·INV-001']);
        $this->assertDatabaseHas('invoice_lines', ['business_id' => $biz->id, 'description' => 'demo·Service 1']);
        $this->assertDatabaseHas('overflow_charges', ['business_id' => $biz->id, 'reference_id' => 'demo·ref123']);
        $this->assertDatabaseHas('credit_terms', ['business_id' => $biz->id, 'terms_type' => 'net_30']);
        $this->assertDatabaseHas('payments', ['business_id' => $biz->id, 'idempotency_key' => 'demo·idem_fail_1']);
        $this->assertDatabaseHas('payments', ['business_id' => $biz->id, 'idempotency_key' => 'demo·idem_fail_2']);
        $this->assertDatabaseHas('decline_deferrals', ['business_id' => $biz->id]);
        $this->assertDatabaseHas('plugin_installs', ['business_id' => $biz->id, 'site_url' => 'demo·https://active.example']);
        $this->assertDatabaseHas('credit_ledger_entries', ['business_id' => $biz->id, 'description' => 'demo·Plan credit']);
        $this->assertDatabaseHas('meters', ['business_id' => $biz->id, 'meter_type' => 'demo·sms_segments']);
        $this->assertDatabaseHas('invoices', ['business_id' => $biz->id, 'invoice_number' => 'demo·AR-001']);
        $this->assertDatabaseHas('payment_plans', ['business_id' => $biz->id, 'installment_amount_cents' => 30000]);
        $this->assertDatabaseHas('invoices', ['business_id' => $biz->id, 'invoice_number' => 'demo·AR-003']);
        $this->assertDatabaseHas('ar_collections_packages', ['business_id' => $biz->id, 'partner' => null]);
        $this->assertDatabaseHas('card_tokens', ['business_id' => $biz->id, 'gateway_customer_id' => 'demo·cus_4242']);
        $this->assertDatabaseHas('card_tokens', ['business_id' => $biz->id, 'last_four' => '5100']);
        $this->assertDatabaseHas('accounting_sync_conflicts', ['business_id' => $biz->id, 'transaction_ref' => 'demo·TXN-4625', 'status' => 'open']);
        $this->assertDatabaseHas('sync_runs', ['business_id' => $biz->id, 'records_synced' => 248]);
        $this->assertDatabaseHas('approval_items', ['business_id' => $biz->id, 'subject' => 'demo·Refund for the Ridgeline callback']);
        $this->assertDatabaseHas('reconciliation_runs', ['business_id' => $biz->id, 'discrepancy_reason' => 'demo·A refund landed after the payout closed']);
        $this->assertDatabaseHas('payments', ['business_id' => $biz->id, 'gateway_charge_id' => 'demo·ch_12345']);
        $this->assertDatabaseHas('ingest_sources', ['business_id' => $biz->id, 'source_name' => 'demo·HubSpot contacts']);

        $c1 = Invoice::count();
        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'C-Billing,X-104,X-198,X-199,X-211,X-120,X-173,X-202,X-156'])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->assertEquals($c1, Invoice::count());

        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'C-Billing,X-104,X-198,X-199,X-211,X-120,X-173,X-202,X-156', '--purge' => true])->assertExitCode(0);

        Tenancy::set($biz->id);

        $this->assertDatabaseMissing('invoices', ['business_id' => $biz->id, 'invoice_number' => 'demo·INV-001']);
        $this->assertDatabaseMissing('invoice_lines', ['business_id' => $biz->id, 'description' => 'demo·Service 1']);
        $this->assertDatabaseMissing('overflow_charges', ['business_id' => $biz->id, 'reference_id' => 'demo·ref123']);
        $this->assertDatabaseMissing('credit_terms', ['business_id' => $biz->id, 'terms_type' => 'net_30']);
        $this->assertDatabaseMissing('payments', ['business_id' => $biz->id, 'idempotency_key' => 'demo·idem_fail_1']);
        $this->assertDatabaseMissing('payments', ['business_id' => $biz->id, 'idempotency_key' => 'demo·idem_fail_2']);
        $this->assertDatabaseMissing('decline_deferrals', ['business_id' => $biz->id]);
        $this->assertDatabaseMissing('plugin_installs', ['business_id' => $biz->id, 'site_url' => 'demo·https://active.example']);
        $this->assertDatabaseMissing('credit_ledger_entries', ['business_id' => $biz->id, 'description' => 'demo·Plan credit']);
        $this->assertDatabaseMissing('meters', ['business_id' => $biz->id, 'meter_type' => 'demo·sms_segments']);
        $this->assertDatabaseMissing('invoices', ['business_id' => $biz->id, 'invoice_number' => 'demo·AR-001']);
        $this->assertDatabaseMissing('payment_plans', ['business_id' => $biz->id, 'installment_amount_cents' => 30000]);
        $this->assertDatabaseMissing('invoices', ['business_id' => $biz->id, 'invoice_number' => 'demo·AR-003']);
        $this->assertDatabaseMissing('ar_collections_packages', ['business_id' => $biz->id, 'partner' => null]);
        $this->assertDatabaseMissing('card_tokens', ['business_id' => $biz->id, 'gateway_customer_id' => 'demo·cus_4242']);
        $this->assertDatabaseMissing('card_tokens', ['business_id' => $biz->id, 'last_four' => '5100']);
        $this->assertDatabaseMissing('accounting_sync_conflicts', ['business_id' => $biz->id, 'transaction_ref' => 'demo·TXN-4625', 'status' => 'open']);
        $this->assertDatabaseMissing('sync_runs', ['business_id' => $biz->id, 'records_synced' => 248]);
        $this->assertDatabaseMissing('approval_items', ['business_id' => $biz->id, 'subject' => 'demo·Refund for the Ridgeline callback']);
        $this->assertDatabaseMissing('reconciliation_runs', ['business_id' => $biz->id, 'discrepancy_reason' => 'demo·A refund landed after the payout closed']);
        $this->assertDatabaseMissing('payments', ['business_id' => $biz->id, 'gateway_charge_id' => 'demo·ch_12345']);
        $this->assertDatabaseMissing('ingest_sources', ['business_id' => $biz->id, 'source_name' => 'demo·HubSpot contacts']);
    }

    public function test_the_money_screens_show_the_demo_rows(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'C-Billing,X-104,X-198,X-199,X-211,X-120,X-173,X-202,X-156'])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->actingAs($owner);

        $this->get(route('x-199.money-paid-today'))->assertOk()->assertSee('demo·INV-001');
        $this->get(route('x-199.unpaid'))->assertOk()->assertSee('demo·INV-002');
        $this->get(route('x-199.declines'))->assertOk()->assertSee('demo·fail_active');
        $this->get(route('x-199.invoices'))->assertOk()->assertSee('demo·INV-001');
        $this->get(route('x-199.credits'))->assertOk()->assertSee('demo·John');
        $this->get(route('x-104.install-count'))->assertOk()->assertSee('Active plugin sites: 1');
        $this->get(route('x-104.plugin-settings-page'))->assertOk()->assertSee('demo·https://active.example');
        $this->get(route('c-billing.mrr'))->assertOk()->assertSee('demo·Plan credit')->assertSee('demo·sms_segments');
        $this->get(route('c-billing.revenue-recovery'))->assertOk()->assertSee('No ladder on this screen yet.');
        $this->get(route('c-billing.dunning-board'))->assertOk()->assertSee('Nothing on this board yet.');
        $this->get(route('c-billing.credits'))->assertOk()->assertSee('demo·sms_segments');
        $this->get(route('c-billing.revenue-recovery'))->assertOk()->assertSee('No ladder on this screen yet.');
        $this->get(route('c-billing.dunning-board'))->assertOk()->assertSee('Nothing on this board yet.');
        $this->get(route('x-211.ageing-by-reason'))->assertOk()->assertSee('demo·AR-001')->assertSee('Customer promised to pay');
        $this->get(route('x-211.paymentplan-builder'))->assertOk()->assertSee('demo·AR-002')->assertSee('3 × 300.00 monthly');
        $this->get(route('x-211.collections-package-preview'))->assertOk()->assertSee('demo·AR-001')->assertSee('demo·AR-003')->assertSee('waiting on a collections partner');
        $this->get(route('x-211.invoice-thread-beside'))->assertOk()->assertSee('demo·AR-001')->assertSee('Why is it unpaid?');
        $this->get(route('x-120.card-screen'))->assertOk()->assertSee('Card ending in 4242')->assertSee('Card Expiring Soon')->assertSee('5100');
        $this->get(route('x-173.conflicts-list'))->assertOk()->assertSee('demo·TXN-4625')->assertSee('Resolved by a person');
        $this->get(route('x-173.sync-error-rate'))->assertOk()->assertSee('548 lines seen')->assertSee('2 conflicts')->assertSee('under 1% conflicts');
        $this->get(route('x-202.queue'))->assertOk()->assertSee('demo·Refund for the Ridgeline callback');
        $this->get(route('x-202.audit-export'))->assertOk()->assertSee('demo·Renewal with an ambiguous clause');
        $this->get(route('x-198.reconciliation-discrepancies'))->assertOk()->assertSee('demo·po_0001');
        $this->get(route('x-198.connect-card'))->assertOk()->assertSee('demo·acct_ridgeline');
        $this->get(route('x-156.ingest-volume-by'))->assertOk()->assertSee('demo·HubSpot contacts');
        $this->get(route('x-156.rejectedrows-list'))->assertOk()->assertSee('demo·missing email on the source row');
    }
}
