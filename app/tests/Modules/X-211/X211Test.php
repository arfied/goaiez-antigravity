<?php

declare(strict_types=1);

namespace Tests\Modules\X211;

use App\Modules\X121\Models\Person;
use App\Modules\X199\Models\Invoice;
use App\Modules\X211\Actions\ArApplyLateFeeAction;
use App\Modules\X211\Actions\ArForceAchAction;
use App\Modules\X211\Actions\ArLogOfflinePaymentAction;
use App\Modules\X211\Actions\ArOfferPlanAction;
use App\Modules\X211\Actions\ArPackageForCollectionsAction;
use App\Modules\X211\Domain\ArEngine;
use App\Modules\X211\Events\ArFeeApplied;
use App\Modules\X211\Events\ArPackaged;
use App\Modules\X211\Events\ArPlanAccepted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X211Test extends TestCase
{
    private ArEngine $engine;

    private ArApplyLateFeeAction $lateFeeAction;

    private ArOfferPlanAction $planAction;

    private ArForceAchAction $achAction;

    private ArLogOfflinePaymentAction $offlineAction;

    private ArPackageForCollectionsAction $collectionsAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new ArEngine;
        $this->lateFeeAction = new ArApplyLateFeeAction($this->engine);
        $this->planAction = new ArOfferPlanAction($this->engine);
        $this->achAction = new ArForceAchAction;
        $this->offlineAction = new ArLogOfflinePaymentAction($this->engine);
        $this->collectionsAction = new ArPackageForCollectionsAction($this->engine);
    }

    /**
     * (R245) DECISION TEST: Late Fee Capping, Payment Plans, Offline Reconciliation & Collections Bundle
     */
    public function test_r245_ar_recovery_suite(): void
    {
        Event::fake([ArFeeApplied::class, ArPlanAccepted::class, ArPackaged::class]);

        $biz = TestCase::provisionTenant(['name' => 'AR Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'Overdue', 'last_name' => 'Client']);

        $invoice = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-AR-101',
            'total_cents' => 60000, // $600.00
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(15)->toDateString(),
        ]);

        // 1. Late fee capped at 10% or $50 (for $600 invoice, 10% is $60, max cap is $50 = 5000 cents)
        $feeRes = $this->lateFeeAction->handle($biz->id, $invoice->id, 7500);
        $this->assertEquals(5000, $feeRes['applied_fee_cents']);
        Event::assertDispatched(ArFeeApplied::class);

        // 2. Offer structured installment plan (3 installments of $200)
        $plan = $this->planAction->handle($biz->id, $invoice->id, 3, 'monthly');
        $this->assertEquals(3, $plan->installments_count);
        $this->assertEquals(20000, $plan->installment_amount_cents);
        $this->assertEquals('accepted', $plan->status);
        Event::assertDispatched(ArPlanAccepted::class);

        // 3. Log offline payment ($200 cash installment)
        $offlinePay = $this->offlineAction->handle($biz->id, $invoice->id, 20000, 'cash', 'REC-001');
        $this->assertEquals(20000, $offlinePay->amount_cents);

        $invoiceFresh = Invoice::where('business_id', $biz->id)->find($invoice->id);
        $this->assertEquals(20000, $invoiceFresh->paid_cents);

        // 4. Force ACH
        $achRes = $this->achAction->handle($biz->id, $invoice->id, 'ba_token_999');
        $this->assertEquals('ach_direct_debit', $achRes['payment_method_forced']);

        // 5. Package for collections
        $collectionsRes = $this->collectionsAction->handle($biz->id, $invoice->id);
        $this->assertEquals('packaged_collections', $collectionsRes['status']);
        $this->assertStringContainsString('.zip', $collectionsRes['bundle_url']);
        Event::assertDispatched(ArPackaged::class);
    }

    /**
     * [G1-65]
     */
    public function test_g1_65_collections_transmission_is_human_action(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'AR Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'Overdue', 'last_name' => 'Client']);
        $invoice = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-AR-103',
            'total_cents' => 60000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(15)->toDateString(),
        ]);

        $res = $this->engine->packageForCollections($biz->id, $invoice->id, true);
        $this->assertSame('packaged_collections', $res['status']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Collections transmission is a human action only');
        $this->engine->packageForCollections($biz->id, $invoice->id, false);
    }

    /**
     * [G1-74]
     */
    public function test_g1_74_offline_payment_needs_reference_or_photo(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'AR Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'Overdue', 'last_name' => 'Client']);
        $invoice = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-AR-104',
            'total_cents' => 60000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(15)->toDateString(),
        ]);

        $pay = $this->engine->logOfflinePayment($biz->id, $invoice->id, 10000, 'check', 'REF-001');
        $this->assertEquals(10000, $pay->amount_cents);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Offline payment needs a reference or a photo');
        $this->engine->logOfflinePayment($biz->id, $invoice->id, 10000, 'check', null, null);
    }

    /**
     * [N-037]
     */
    public function test_n_037_fee_with_no_term_refused(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'AR Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'Overdue', 'last_name' => 'Client']);

        $invoice = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-AR-102',
            'total_cents' => 60000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(15)->toDateString(),
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('A fee with no matching TERM in the agreement is refused');

        $this->engine->applyLateFee($biz->id, $invoice->id, 7500, false);
    }

    /**
     * [G1-61]
     */
    public function test_g1_61_past_the_threshold_routes_to_a_financing_partner(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'AR Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'Overdue', 'last_name' => 'Client']);
        
        $invoiceSub = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-AR-SUB',
            'total_cents' => 40000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(15)->toDateString(),
        ]);
        
        $invoiceOver = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-AR-OVER',
            'total_cents' => 60000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(15)->toDateString(),
        ]);

        Event::fake([ArPlanAccepted::class]);

        $plan = $this->engine->offerPlan($biz->id, $invoiceSub->id, 3, 'monthly', 50000);
        $this->assertInstanceOf(\App\Modules\X211\Models\PaymentPlan::class, $plan);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('routes to a financing partner');

        $this->engine->offerPlan($biz->id, $invoiceOver->id, 3, 'monthly', 50000);
    }

    /**
     * [G1-70]
     */
    public function test_g1_70_we_never_hold_the_paper(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'AR Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'Overdue', 'last_name' => 'Client']);
        $invoice = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-AR-PAPER',
            'total_cents' => 60000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(15)->toDateString(),
        ]);

        $countBefore = \App\Modules\X211\Models\PaymentPlan::count();

        try {
            $this->engine->offerPlan($biz->id, $invoice->id, 3, 'monthly', 50000);
        } catch (\DomainException $e) {
            $this->assertStringContainsString('routes to a financing partner', $e->getMessage());
        }

        $countAfter = \App\Modules\X211\Models\PaymentPlan::count();
        $this->assertEquals($countBefore, $countAfter);
    }
}
