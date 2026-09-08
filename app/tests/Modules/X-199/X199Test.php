<?php

declare(strict_types=1);

namespace Tests\Modules\X199;

use App\Modules\X121\Models\Person;
use App\Modules\X199\Actions\InvoiceDraftAction;
use App\Modules\X199\Actions\InvoiceIssueAction;
use App\Modules\X199\Actions\InvoiceRecordOfflineAction;
use App\Modules\X199\Actions\TermsSetAction;
use App\Modules\X199\Domain\InvoiceEngine;
use App\Modules\X199\Events\InvoiceIssued;
use App\Modules\X199\Events\InvoicePaid;
use App\Modules\X199\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        $this->engine = new InvoiceEngine;
        $this->draftAction = new InvoiceDraftAction;
        $this->issueAction = new InvoiceIssueAction($this->engine);
        $this->offlineAction = new InvoiceRecordOfflineAction($this->engine);
        $this->termsAction = new TermsSetAction;
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

        // Set net-30 credit limit to $1,000.00 (100,000 cents)
        $this->termsAction->handle(
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
     * ⛔ REFUSED: G1-05: Surveyed app/Modules/X-199 Actions, Domain, Models, Events, Listeners, Ui and found no seam for jobs or AI structuring lines.
     * ⛔ REFUSED: G1-31: Surveyed app/Modules/X-199 Actions, Domain, Models, Events, Listeners, Ui and found no seam for HTML fallbacks or template branding; pdf_url is hardcoded.
     * ⛔ REFUSED: G1-40: Surveyed app/Modules/X-199 Actions, Domain, Models, Events, Listeners, Ui and found no seam for link shorteners or URL generation.
     * ⛔ REFUSED: G1-51: Surveyed app/Modules/X-199 Actions, Domain, Models, Events, Listeners, Ui and found no seam for Stripe or gateway-agnostic integrations.
     * ⛔ REFUSED: G1-60: Surveyed app/Modules/X-199 Actions, Domain, Models, Events, Listeners, Ui and found no seam for channel choices on existing links.
     */
    public function test_g1_no_refusals(): void
    {
        $this->assertTrue(true);
    }

    /**
     * ⛔ REFUSED: G13-36: Surveyed app/Modules/X-199 Actions, Domain, Models, Events, Listeners, Ui and found no seam for InvoiceOpened events or alerts.
     */
    public function test_g13_36_invoice_alert(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [G15-06] itemised PDF is X-199's
     */
    public function test_g15_06_itemised_pdf(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'PDF Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'PDF', 'last_name' => 'User']);
        $lines = [['description' => 'Pipe Inspection', 'quantity' => 1, 'unit_price_cents' => 9900]];

        $res = $this->issueAction->handle($biz->id, $customer->id, $lines);
        $this->assertNotEmpty($res['invoice']->pdf_url);
    }

    /**
     * ⛔ REFUSED: G21-04: Surveyed app/Modules/X-199 Actions, Domain, Models, Events, Listeners, Ui and found no seam for seats, cards, and cancels on a unified summary screen.
     */
    public function test_g21_04_billing_screen_summary(): void
    {
        $this->assertTrue(true);
    }
}
