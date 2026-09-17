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
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'C-Billing,X-104,X-199,X-211'])->assertExitCode(0);

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

        $c1 = Invoice::count();
        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'C-Billing,X-104,X-199,X-211'])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->assertEquals($c1, Invoice::count());

        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'C-Billing,X-104,X-199,X-211', '--purge' => true])->assertExitCode(0);

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
    }

    public function test_the_money_screens_show_the_demo_rows(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'C-Billing,X-104,X-199,X-211'])->assertExitCode(0);

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
    }
}
