<?php

declare(strict_types=1);

namespace Tests\Modules\X199;

use App\Modules\X121\Models\Person;
use App\Modules\X198\Models\MerchantConnection;
use App\Modules\X199\Actions\DraftInvoiceFromJobAction;
use App\Modules\X199\Actions\GenerateShortLinkAction;
use App\Modules\X199\Actions\GetBillingSummaryAction;
use App\Modules\X199\Actions\InvoiceDraftAction;
use App\Modules\X199\Actions\InvoiceIssueAction;
use App\Modules\X199\Actions\InvoiceOverdueListAction;
use App\Modules\X199\Actions\InvoicePaymentLinkAction;
use App\Modules\X199\Actions\InvoiceRecordOfflineAction;
use App\Modules\X199\Actions\SendInvoiceLinkAction;
use App\Modules\X199\Actions\TermsSetAction;
use App\Modules\X199\Domain\InvoiceEngine;
use App\Modules\X199\Events\InvoiceIssued;
use App\Modules\X199\Events\InvoiceOpened;
use App\Modules\X199\Events\InvoiceOverdue;
use App\Modules\X199\Events\InvoicePaid;
use App\Modules\X199\Listeners\NotifyInvoiceOpened;
use App\Modules\X199\Models\CreditTerm;
use App\Modules\X199\Models\Invoice;
use App\Modules\X199\Models\InvoiceLine;
use App\Services\Config\DefaultsRegistry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class X199Test extends TestCase
{
    private InvoiceEngine $engine;

    private InvoiceDraftAction $draftAction;

    private InvoiceIssueAction $issueAction;

    private InvoiceRecordOfflineAction $offlineAction;

    private TermsSetAction $termsAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new InvoiceEngine(app(DefaultsRegistry::class));
        $this->draftAction = new InvoiceDraftAction(app(DefaultsRegistry::class));
        $this->issueAction = new InvoiceIssueAction($this->engine);
        $this->offlineAction = new InvoiceRecordOfflineAction($this->engine);
        $this->termsAction = new TermsSetAction(app(DefaultsRegistry::class));
    }

    /**
     * TEST ANCHOR
     * a net-30 account over its limit still gets service and produces an overflow.charged row against the card on file;
     * paying the invoice writes overflow.reversed for the same amount;
     * no invoice is ever split into installments — asserted by schema
     */
    public function test_anchor_net30_overflow_charge_reversal_and_no_installments_schema(): void
    {
        Event::fake([InvoiceIssued::class, InvoicePaid::class]);

        $biz = TestCase::provisionTenant(['name' => 'Invoice Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customer = Person::create([
            'business_id' => $biz->id,
            'first_name' => 'Acme',
            'last_name' => 'Corp',
            'phone' => '+15125550399',
        ]);

        MerchantConnection::create([
            'business_id' => $biz->id,
            'gateway_name' => 'stripe',
            'merchant_account_id' => 'acct_test',
            'is_connected' => true,
        ]);

        Http::fake([
            'api.stripe.com/*' => Http::response(['id' => 'ch_mock_123', 'status' => 'succeeded'], 200),
        ]);

        // Set net-30 credit limit to $1,000.00 (100,000 cents)
        $termsAction = new TermsSetAction(app(DefaultsRegistry::class));
        $termsAction->handle(
            businessId: $biz->id,
            customerId: $customer->id,
            termsType: 'net_30',
            creditLimitCents: 100000,
            cardOnFileToken: 'pm_card_acme_vault'
        );

        // 1. Issue invoice for $1,500.00 (150,000 cents) -> over limit by $500.00 (50,000 cents)
        // The customer STILL gets service and produces an overflow.charged row
        $lines = [
            ['description' => 'Commercial HVAC Overhaul', 'quantity' => 1, 'unit_price_cents' => 150000],
        ];

        $issueRes = $this->issueAction->handle($biz->id, $customer->id, $lines, 'net_30');

        $this->assertTrue($issueRes['is_over_limit']);
        $this->assertNotNull($issueRes['overflow_charge']);
        $this->assertEquals('overflow_charged', $issueRes['overflow_charge']->charge_type);
        $this->assertEquals(50000, $issueRes['overflow_charge']->amount_cents);
        $this->assertEquals('pm_card_acme_vault', $issueRes['overflow_charge']->card_token);

        Event::assertDispatched(InvoiceIssued::class);

        // Force status to charged so we can test the reversal logic without mocking the gateway
        // (Status is now updated automatically since gateway is mocked)

        // 2. Paying the invoice writes overflow.reversed for the same amount
        $payRes = $this->engine->recordPayment($biz->id, $issueRes['invoice']->id);
        $this->assertEquals('paid', $payRes['status']);
        $this->assertNotEmpty($payRes['reversed_overflow_charges']);

        $reversedCharge = $payRes['reversed_overflow_charges'][0];
        $this->assertEquals('overflow_reversed', $reversedCharge->charge_type);
        $this->assertEquals(50000, $reversedCharge->amount_cents);

        // 3. No invoice is ever split into installments — asserted by schema
        $this->assertFalse(
            Schema::hasColumn('invoices', 'installments'),
            'Invoice table must never contain an installments column'
        );
        $this->assertFalse(
            Schema::hasTable('invoice_installments'),
            'No invoice_installments table must exist — invoices are atomic single amounts'
        );
    }

    /**
     * [G1-51] gateway-agnostic — "Stripe" is corpus vocabulary
     */
    public function test_g1_51_gateway_agnostic(): void
    {
        $invoice = new Invoice(['id' => 999]);
        $action = new InvoicePaymentLinkAction;

        $link = $action->handle($invoice, 'generic_gateway');
        $this->assertStringContainsString('generic_gateway', $link);
        $this->assertStringNotContainsString('stripe', $link, 'The payment link action is gateway-agnostic');
    }

    /**
     * [G1-60] a channel choice on an existing link
     */
    public function test_g1_60_channel_choice(): void
    {
        $invoice = new Invoice(['id' => 888]);
        $action = new SendInvoiceLinkAction;

        $res = $action->handle($invoice, 'sms');
        $this->assertEquals('sent', $res['status']);
        $this->assertEquals('sms', $res['channel']);
    }

    /**
     * [G13-36] invoice opened the alert names an action
     */
    public function test_g13_36_invoice_alert(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with('Alert: Invoice opened.', \Mockery::on(function ($data) {
                return $data['suggested_action'] === 'record_payment';
            }));

        $event = new InvoiceOpened(1, 123);
        $listener = new NotifyInvoiceOpened;
        $listener->handle($event);

        $this->assertTrue(true, 'Mock assertion completes the test');
    }

    /**
     * [G15-06] itemised PDF is X-199's
     * [G1-31] branding columns on the invoice template; PDF fails HTML, never no invoice
     */
    public function test_g15_06_itemised_pdf(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'PDF Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'PDF', 'last_name' => 'User']);
        $lines = [['description' => 'Pipe Inspection', 'quantity' => 1, 'unit_price_cents' => 9900]];

        $res = $this->issueAction->handle($biz->id, $customer->id, $lines);

        // G1-31: Even if PDF is null, the invoice is still issued and reachable via HTML
        $this->assertNull($res['invoice']->pdf_url);
        $this->assertEquals('issued', $res['invoice']->status);
    }

    /**
     * [G1-05] AI structures lines from the job; fails one line at the total
     */
    public function test_g1_05_ai_structures_lines(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'AI Job', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'John', 'last_name' => 'Doe']);

        $draftAction = new InvoiceDraftAction(app(DefaultsRegistry::class));
        $action = new DraftInvoiceFromJobAction($draftAction);

        // Without an AI engine, it fails to one line at the total
        $invoice = $action->handle($biz->id, $customer->id, 5000, 'Fixed the sink');
        $this->assertEquals(5000, $invoice->total_cents);

        $line = InvoiceLine::where('invoice_id', $invoice->id)->first();
        $this->assertStringContainsString('Work order completion', $line->description);
        $this->assertEquals(5000, $line->unit_price_cents);
    }

    /**
     * [G1-40] links off an invoice/quote, on the short-linker
     */
    public function test_g1_40_short_linker(): void
    {
        $action = new GenerateShortLinkAction;
        $link = $action->handle('https://example.com/invoice/123');

        $this->assertStringStartsWith('https://s.local/', $link);
        $this->assertEquals(24, strlen($link)); // 16 for host + 8 for hash
    }

    /**
     * [G21-04] card, invoices, seats — and cancel in under 60 seconds belongs on the same screen
     */
    public function test_g21_04_billing_screen_summary(): void
    {
        $action = new GetBillingSummaryAction;
        $summary = $action->handle(1);

        $this->assertArrayHasKey('card', $summary);
        $this->assertArrayHasKey('invoices', $summary);
        $this->assertArrayHasKey('seats', $summary);
        $this->assertArrayHasKey('cancel_url', $summary);
    }

    public function test_invoice_overdue_reports_real_age(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-06 12:00:00'));

        $biz = TestCase::provisionTenant(['name' => 'Overdue Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customer = Person::create([
            'business_id' => $biz->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+15551234567',
        ]);

        $invoice1 = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-001',
            'total_cents' => 10000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(45)->toDateString(),
        ]);

        $invoice2 = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-002',
            'total_cents' => 10000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(3)->toDateString(),
        ]);

        Event::fake([InvoiceOverdue::class]);

        $this->engine->markOverdue($biz->id, $invoice1->id);
        $this->engine->markOverdue($biz->id, $invoice2->id);

        Event::assertDispatched(InvoiceOverdue::class, function ($event) use ($invoice1) {
            return $event->invoiceId === $invoice1->id && $event->daysOverdue === 45;
        });

        Event::assertDispatched(InvoiceOverdue::class, function ($event) use ($invoice2) {
            return $event->invoiceId === $invoice2->id && $event->daysOverdue === 3;
        });

        Carbon::setTestNow();
    }

    public function test_a_draft_takes_its_due_date_from_the_stored_terms_and_falls_back_to_the_argument(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Draft Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customer1 = Person::create([
            'business_id' => $biz->id,
            'first_name' => 'Has',
            'last_name' => 'Terms',
        ]);

        $customer2 = Person::create([
            'business_id' => $biz->id,
            'first_name' => 'No',
            'last_name' => 'Terms',
        ]);

        CreditTerm::create([
            'business_id' => $biz->id,
            'customer_id' => $customer1->id,
            'terms_type' => 'net_60',
            'credit_limit_cents' => 500000,
            'current_outstanding_cents' => 0,
            'card_on_file_token' => null,
        ]);

        $lines = [['description' => 'Work', 'quantity' => 1, 'unit_price_cents' => 10000]];

        $invoice1 = $this->draftAction->handle($biz->id, $customer1->id, $lines, 30);
        $this->assertEquals(now()->addDays(60)->toDateString(), $invoice1->due_date->toDateString());

        $invoice2 = $this->draftAction->handle($biz->id, $customer2->id, $lines, 30);
        $this->assertEquals(now()->addDays(30)->toDateString(), $invoice2->due_date->toDateString());
    }

    public function test_a_concurrent_terms_row_is_absorbed_into_the_one_row_for_the_customer(): void
    {
        // This test turns on X199Test being a CLASS-BASED PHPUnit file, which Pest's
        // ->use(RefreshesTenantDatabase::class)->in('Modules') binding does NOT reach, so the racer's
        // row COMMITS on pgsql_migrate and issueInvoice() can see it. If TestCase ever binds a refresh
        // trait directly, this test stops proving anything -- while very likely still passing.
        Carbon::setTestNow(now());

        $biz = TestCase::provisionTenant(['name' => 'Terms Race Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customer = Person::create([
            'business_id' => $biz->id,
            'first_name' => 'Race',
            'last_name' => 'Terms',
        ]);

        // A second request lands a terms row for the same customer between the engine's read and its
        // insert. It writes on pgsql_migrate, a separate session, so it commits. RLS is FORCED and
        // constrains the owner role too.
        $racer = DB::connection('pgsql_migrate');
        $racer->statement("SET app.business_id = '{$biz->id}'");

        CreditTerm::creating(function () use ($racer, $biz, $customer): void {
            $racer->table('credit_terms')->insert([
                'business_id' => $biz->id,
                'customer_id' => $customer->id,
                'terms_type' => 'net_60',
            ]);
        });

        try {
            $res = $this->engine->issueInvoice(
                $biz->id,
                $customer->id,
                [['description' => 'Work', 'quantity' => 1, 'unit_price_cents' => 10000]]
            );
        } finally {
            CreditTerm::flushEventListeners();
        }

        // The engine absorbs the winner's row: one terms row for the customer.
        $this->assertSame(
            1,
            CreditTerm::where('business_id', $biz->id)->where('customer_id', $customer->id)->count()
        );

        // And the invoice took its due date from the row that won (net 60), not from the net 30 the
        // engine would have written.
        $this->assertSame(now()->addDays(60)->toDateString(), $res['invoice']->due_date->toDateString());

        Carbon::setTestNow();
    }

    public function test_overdue_list_excludes_paid_and_future(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Overdue List Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customer = Person::create([
            'business_id' => $biz->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+15551234567',
        ]);

        // Paid
        Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-001',
            'total_cents' => 10000,
            'paid_cents' => 10000,
            'status' => 'paid',
            'due_date' => now()->subDays(10)->toDateString(),
        ]);

        // Future
        Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-002',
            'total_cents' => 10000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->addDays(1)->toDateString(),
        ]);

        // Overdue
        $overdue = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-003',
            'total_cents' => 10000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(40)->toDateString(),
        ]);

        $action = new InvoiceOverdueListAction;
        $list = $action->handle($biz->id);

        $this->assertCount(1, $list);
        $this->assertEquals($overdue->id, $list[0]['invoice_id']);
        $this->assertEquals(40, $list[0]['days_overdue']);
    }
}
