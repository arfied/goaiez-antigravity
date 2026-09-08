<?php

declare(strict_types=1);

namespace Tests\Modules\X211;

use App\Models\User;
use App\Modules\X121\Models\Person;
use App\Modules\X199\Models\Invoice;
use App\Modules\X211\Actions\ArApplyLateFeeAction;
use App\Modules\X211\Actions\ArForceAchAction;
use App\Modules\X211\Actions\ArLogOfflinePaymentAction;
use App\Modules\X211\Actions\ArOfferPlanAction;
use App\Modules\X211\Actions\ArPackageForCollectionsAction;
use App\Modules\X211\Domain\ArEngine;
use App\Modules\X211\Domain\FeeWithoutTermException;
use App\Modules\X211\Domain\NoResolutionAttemptException;
use App\Modules\X211\Domain\PlanPastThresholdException;
use App\Modules\X211\Domain\UnreferencedPaymentException;
use App\Modules\X211\Events\ArFeeApplied;
use App\Modules\X211\Events\ArOverdue;
use App\Modules\X211\Events\ArPackaged;
use App\Modules\X211\Events\ArPlanAccepted;
use App\Modules\X211\Models\ArCollectionsPackage;
use App\Modules\X211\Models\ArDunningAction;
use App\Modules\X211\Models\ArPlanTerm;
use App\Modules\X211\Models\PaymentPlan;
use App\Modules\X211\Models\ReceivableState;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Artisan;
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

        ArPlanTerm::create(['business_id' => $biz->id, 'late_fee_percent' => 10, 'late_fee_cap_cents' => 5000]);

        // 1. Late fee capped at 10% or $50 (for $600 invoice, 10% is $60, max cap is $50 = 5000 cents)
        $feeRes = $this->lateFeeAction->handle($biz->id, $invoice->id, 7500);
        $this->assertEquals(5000, $feeRes['applied_fee_cents']);
        Event::assertDispatched(ArFeeApplied::class);

        // 2. Offer structured installment plan (3 installments of $200)
        $plan = $this->planAction->handle($biz->id, $invoice->id, 3, 'monthly');
        $this->assertEquals(3, $plan->installments_count);
        $this->assertEquals(20000, $plan->installment_amount_cents);
        $this->assertEquals('offered', $plan->status);
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
        $this->assertNull($collectionsRes['bundle_url']);
        Event::assertDispatched(ArPackaged::class);
    }

    /**
     * G1-71 — a fee with no matching term in the agreement is refused before any write; P-193 — the percent and the cap are the tenant's row
     */
    public function test_a_fee_with_no_matching_term_is_refused(): void
    {
        Event::fake([ArFeeApplied::class]);

        $biz = TestCase::provisionTenant(['name' => 'No-term Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'Late', 'last_name' => 'Payer']);
        $invoice = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-AR-201',
            'total_cents' => 60000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(15)->toDateString(),
        ]);

        try {
            $this->lateFeeAction->handle($biz->id, $invoice->id, 7500);
            $this->fail('a fee with no term was applied');
        } catch (FeeWithoutTermException $e) {
            $this->assertStringContainsString('no matching term is refused', $e->getMessage());
            $this->assertStringContainsString('INV-AR-201', $e->getMessage());
        }

        $this->assertSame(0, ReceivableState::where('business_id', $biz->id)->where('invoice_id', $invoice->id)->count());
        $this->assertSame(0, ArPlanTerm::where('business_id', $biz->id)->count(), 'a refused fee created the terms row');
        Event::assertNotDispatched(ArFeeApplied::class);

        // The term is a row: write one with no cap and the same call applies 5 % of the total, not the old $50 literal.
        ArPlanTerm::firstOrCreate(['business_id' => $biz->id])->update(['late_fee_percent' => 5]);
        $applied = $this->lateFeeAction->handle($biz->id, $invoice->id, 7500);
        $this->assertSame(3000, $applied['applied_fee_cents']);
        Event::assertDispatched(ArFeeApplied::class);
    }

    /**
     * [G1-61], [G1-70]
     */
    public function test_g1_61_g1_70_plan_past_threshold_is_refused(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Threshold Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'T', 'last_name' => 'T']);
        $invoice = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-THR-01',
            'total_cents' => 100000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(10)->toDateString(),
        ]);

        $thrown = false;
        try {
            $this->planAction->handle($biz->id, $invoice->id, 12, 'monthly');
        } catch (PlanPastThresholdException $e) {
            $thrown = true;
            $this->assertStringContainsString('routes to a financing partner', $e->getMessage());
        }

        $this->assertTrue($thrown);
        $this->assertSame(0, PaymentPlan::where('business_id', $biz->id)->where('invoice_id', $invoice->id)->count());
    }

    /**
     * [G1-65]
     */
    public function test_g1_65_package_for_collections_refused_without_resolution_attempt(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Col Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'C', 'last_name' => 'C']);
        $invoice = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-COL-01',
            'total_cents' => 50000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(60)->toDateString(),
        ]);

        $thrown = false;
        try {
            $this->collectionsAction->handle($biz->id, $invoice->id);
        } catch (NoResolutionAttemptException $e) {
            $thrown = true;
            $this->assertStringContainsString('Record a resolution attempt first', $e->getMessage());
        }

        $this->assertTrue($thrown);
        $this->assertSame(0, ArCollectionsPackage::where('business_id', $biz->id)->where('invoice_id', $invoice->id)->count());
    }

    /**
     * [N-033]
     */
    public function test_n_033_reason_recorded_routes_to_human_when_needed(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Human Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'H', 'last_name' => 'H']);
        $invoice = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-HUM-01',
            'total_cents' => 20000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

        $engine = app(ArEngine::class);
        $engine->recordReason($biz->id, $invoice->id, 'complaint');

        $escalated = ArDunningAction::where('business_id', $biz->id)->where('invoice_id', $invoice->id)->where('action', 'escalate_to_human')->count();
        $this->assertSame(1, $escalated);
    }

    public function test_sweep_dispatches_aroverdue_for_qualifying_invoice_and_not_when_escalated(): void
    {
        Event::fake([ArOverdue::class]);

        $biz = TestCase::provisionTenant(['name' => 'Sweep Tenant', 'currency' => 'USD']);
        // Assign an owner_user_id so the command can find it during the chunk sweep
        $user = User::factory()->create();
        $biz->update(['owner_user_id' => $user->id]);

        DB::statement("SET app.business_id = '{$biz->id}'");

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'Sweep', 'last_name' => 'Client']);

        $invoice1 = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-SWP-001',
            'total_cents' => 10000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

        $invoice2 = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-SWP-002',
            'total_cents' => 20000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

        // escalate invoice2
        ArDunningAction::create([
            'business_id' => $biz->id,
            'invoice_id' => $invoice2->id,
            'action' => 'escalate_to_human',
            'reason' => 'Silence',
        ]);

        Artisan::call('x211:detect-overdue');

        Event::assertDispatched(ArOverdue::class, function ($event) use ($invoice1) {
            return $event->invoiceId === $invoice1->id;
        });

        Event::assertNotDispatched(ArOverdue::class, function ($event) use ($invoice2) {
            return $event->invoiceId === $invoice2->id;
        });
    }

    public function test_arengine_refuses_invoice_from_another_business(): void
    {
        $biz1 = TestCase::provisionTenant(['name' => 'Biz 1', 'currency' => 'USD']);
        $biz2 = TestCase::provisionTenant(['name' => 'Biz 2', 'currency' => 'USD']);

        DB::statement("SET app.business_id = '{$biz1->id}'");
        $customer = Person::create(['business_id' => $biz1->id, 'first_name' => 'Biz', 'last_name' => 'One']);
        $invoice1 = Invoice::create([
            'business_id' => $biz1->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-BIZ1-001',
            'total_cents' => 10000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

        $this->expectException(ModelNotFoundException::class);
        $this->lateFeeAction->handle($biz2->id, $invoice1->id, 100);
    }

    /**
     * [G1-65]
     */
    public function test_g1_65_collections_transmission_is_human_action(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'AR Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");
        $user = User::factory()->create();

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

        ArDunningAction::create([
            'business_id' => $biz->id,
            'invoice_id' => $invoice->id,
            'action' => 'escalate_to_human',
            'reason' => 'Silence',
        ]);

        $res = $this->engine->packageForCollections($biz->id, $invoice->id, $user->id);
        $this->assertSame('packaged_collections', $res['status']);
        $this->assertSame($user->id, $res['packaged_by_user_id']);

        $package = ArCollectionsPackage::where('invoice_id', $invoice->id)->first();
        $this->assertSame($user->id, $package->packaged_by_user_id);

        $invoice2 = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-AR-104',
            'total_cents' => 60000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(15)->toDateString(),
        ]);

        $this->expectException(NoResolutionAttemptException::class);
        $this->engine->packageForCollections($biz->id, $invoice2->id, $user->id);
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

        $this->expectException(UnreferencedPaymentException::class);
        $this->expectExceptionMessage('must have a reference or photo. Nothing was logged.');
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

        $this->expectException(FeeWithoutTermException::class);
        $this->expectExceptionMessage('a fee with no matching term is refused. Nothing was applied.');

        $this->engine->applyLateFee($biz->id, $invoice->id, 7500);
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
        $this->assertInstanceOf(PaymentPlan::class, $plan);

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

        $countBefore = PaymentPlan::count();

        try {
            $this->engine->offerPlan($biz->id, $invoice->id, 3, 'monthly', 50000);
        } catch (\DomainException $e) {
            $this->assertStringContainsString('routes to a financing partner', $e->getMessage());
        }

        $countAfter = PaymentPlan::count();
        $this->assertEquals($countBefore, $countAfter);
    }
}
