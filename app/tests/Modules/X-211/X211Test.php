<?php

declare(strict_types=1);

namespace Tests\Modules\X211;

use App\Models\User;
use App\Modules\X121\Models\Person;
use App\Modules\X199\Models\Invoice;
use App\Modules\X211\Actions\ArApplyLateFeeAction;
use App\Modules\X211\Actions\ArDunningHistoryAction;
use App\Modules\X211\Actions\ArForceAchAction;
use App\Modules\X211\Actions\ArLogOfflinePaymentAction;
use App\Modules\X211\Actions\ArOfferPlanAction;
use App\Modules\X211\Actions\ArPackageForCollectionsAction;
use App\Modules\X211\Actions\ArRecordReasonAction;
use App\Modules\X211\Domain\AlreadyPackagedException;
use App\Modules\X211\Domain\ArEngine;
use App\Modules\X211\Domain\FeeAtCapException;
use App\Modules\X211\Domain\FeeWithoutTermException;
use App\Modules\X211\Domain\NoResolutionAttemptException;
use App\Modules\X211\Domain\PlanAlreadyOfferedException;
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

    public function test_the_late_fee_cap_is_a_ceiling_on_the_invoice_not_on_each_application(): void
    {
        Event::fake([ArFeeApplied::class]);

        $biz = TestCase::provisionTenant(['name' => 'Cap Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'Cap', 'last_name' => 'Ceiling']);
        $invoice = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-AR-301',
            'total_cents' => 100000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(20)->toDateString(),
        ]);

        ArPlanTerm::create(['business_id' => $biz->id, 'late_fee_percent' => 10, 'late_fee_cap_cents' => 2000]);

        $res1 = $this->lateFeeAction->handle($biz->id, $invoice->id, 1500);
        $this->assertSame(1500, $res1['applied_fee_cents']);
        $this->assertSame(1500, $res1['total_late_fee_cents']);

        $res2 = $this->lateFeeAction->handle($biz->id, $invoice->id, 1500);
        $this->assertSame(500, $res2['applied_fee_cents']);
        $this->assertSame(2000, $res2['total_late_fee_cents']);

        try {
            $this->lateFeeAction->handle($biz->id, $invoice->id, 1500);
            $this->fail('a fee above the cap was applied');
        } catch (FeeAtCapException $e) {
            $this->assertStringContainsString('is already at the term ceiling of 20.00', $e->getMessage());
        }

        $this->assertSame(2000, (int) ReceivableState::where('business_id', $biz->id)->where('invoice_id', $invoice->id)->value('late_fee_cents'));
        Event::assertDispatchedTimes(ArFeeApplied::class, 2);
    }

    public function test_a_second_collections_package_on_one_invoice_is_refused_and_writes_nothing(): void
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

        $this->engine->packageForCollections($biz->id, $invoice->id, $user->id);

        try {
            $this->engine->packageForCollections($biz->id, $invoice->id, $user->id);
            $this->fail('A second package was not refused.');
        } catch (AlreadyPackagedException $e) {
            $this->assertStringContainsString('has already been packaged for collections', $e->getMessage());
        }

        $this->assertSame(1, ArCollectionsPackage::where('business_id', $biz->id)->where('invoice_id', $invoice->id)->count());
    }

    public function test_a_concurrent_escalation_is_absorbed_into_one_escalation_and_one_receivable_state(): void
    {
        // This test turns on X211Test being a CLASS-BASED PHPUnit file, which Pest's
        // ->use(RefreshesTenantDatabase::class)->in('Modules') binding does NOT reach, so the racer's
        // rows COMMIT on pgsql_migrate and recordReason() can see them. If TestCase ever binds a
        // refresh trait directly, this test stops proving anything -- while very likely still passing.
        $biz = TestCase::provisionTenant(['name' => 'Escalation Race Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'R', 'last_name' => 'R']);
        $invoice = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-RACE-01',
            'total_cents' => 20000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

        // A second writer lands the same two rows between firstOrCreate()'s read and its insert. It
        // writes on pgsql_migrate, a separate session, so it commits rather than joining
        // recordReason()'s open transaction. RLS is FORCED and constrains the owner role too.
        $racer = DB::connection('pgsql_migrate');
        $racer->statement("SET app.business_id = '{$biz->id}'");

        ArDunningAction::creating(function (ArDunningAction $action) use ($racer, $biz, $invoice): void {
            if ($action->action !== 'escalate_to_human') {
                return;
            }

            $racer->table('ar_dunning_actions')->insert([
                'business_id' => $biz->id,
                'invoice_id' => $invoice->id,
                'action' => 'escalate_to_human',
                'reason' => 'Escalated by the racer',
            ]);
        });

        ReceivableState::creating(function () use ($racer, $biz, $invoice): void {
            $racer->table('receivable_states')->insert([
                'business_id' => $biz->id,
                'invoice_id' => $invoice->id,
                'status' => 'overdue',
            ]);
        });

        try {
            app(ArEngine::class)->recordReason($biz->id, $invoice->id, 'complaint');
        } finally {
            ArDunningAction::flushEventListeners();
            ReceivableState::flushEventListeners();
        }

        // The loser absorbs the winner's rows: one escalation, one receivable state.
        $this->assertSame(
            1,
            ArDunningAction::where('business_id', $biz->id)
                ->where('invoice_id', $invoice->id)
                ->where('action', 'escalate_to_human')
                ->count()
        );
        $this->assertSame(
            1,
            ReceivableState::where('business_id', $biz->id)->where('invoice_id', $invoice->id)->count()
        );

        // The escalation that survived is the racer's, and recordReason()'s update landed on the
        // receivable state it absorbed.
        $this->assertSame(
            'Escalated by the racer',
            ArDunningAction::where('business_id', $biz->id)
                ->where('invoice_id', $invoice->id)
                ->where('action', 'escalate_to_human')
                ->value('reason')
        );
        $this->assertSame(
            'escalated',
            ReceivableState::where('business_id', $biz->id)->where('invoice_id', $invoice->id)->value('status')
        );
    }

    public function test_a_concurrent_plan_offer_is_refused_and_the_invoice_keeps_one_plan(): void
    {
        // This test turns on X211Test being a CLASS-BASED PHPUnit file, which Pest's
        // ->use(RefreshesTenantDatabase::class)->in('Modules') binding does NOT reach, so the racer's
        // row COMMITS on pgsql_migrate and offerPlan() can see it. If TestCase ever binds a refresh
        // trait directly, this test stops proving anything -- while very likely still passing.
        $biz = TestCase::provisionTenant(['name' => 'Plan Race Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'P', 'last_name' => 'P']);
        $invoice = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-PLAN-RACE',
            'total_cents' => 30000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

        // A second offer stores a plan for this invoice between offerPlan()'s guard and its insert. It
        // writes on pgsql_migrate, a separate session, so it commits. RLS is FORCED and constrains the
        // owner role too.
        $racer = DB::connection('pgsql_migrate');
        $racer->statement("SET app.business_id = '{$biz->id}'");

        PaymentPlan::creating(function () use ($racer, $biz, $invoice): void {
            $racer->table('payment_plans')->insert([
                'business_id' => $biz->id,
                'invoice_id' => $invoice->id,
                'installments_count' => 3,
                'installment_amount_cents' => 10000,
                'frequency' => 'monthly',
                'status' => 'offered',
            ]);
        });

        $refused = null;
        try {
            $this->engine->offerPlan($biz->id, $invoice->id, 2, 'biweekly');
        } catch (PlanAlreadyOfferedException $e) {
            $refused = $e;
        } finally {
            PaymentPlan::flushEventListeners();
        }

        // The invoice keeps one plan.
        $this->assertSame(
            1,
            PaymentPlan::where('business_id', $biz->id)->where('invoice_id', $invoice->id)->count()
        );

        // The loser is refused exactly as the guard refuses a second offer, naming the plan that won
        // (the racer's 3 monthly payments, not the 2 biweekly payments this call asked for).
        $this->assertNotNull($refused);
        $this->assertStringContainsString(
            'INV-PLAN-RACE is already on a plan: 3 monthly payments',
            $refused->getMessage()
        );
    }

    public function test_a_concurrent_collections_package_is_refused_and_the_invoice_keeps_one_package(): void
    {
        // This test turns on X211Test being a CLASS-BASED PHPUnit file, which Pest's
        // ->use(RefreshesTenantDatabase::class)->in('Modules') binding does NOT reach, so the racer's
        // row COMMITS on pgsql_migrate and packageForCollections() can see it. If TestCase ever binds a
        // refresh trait directly, this test stops proving anything -- while very likely still passing.
        $biz = TestCase::provisionTenant(['name' => 'Package Race Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");
        $user = User::factory()->create();

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'K', 'last_name' => 'K']);
        $invoice = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-PKG-RACE',
            'total_cents' => 60000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(15)->toDateString(),
        ]);

        // A resolution attempt is recorded first, or packageForCollections() refuses before its insert.
        ArDunningAction::create([
            'business_id' => $biz->id,
            'invoice_id' => $invoice->id,
            'action' => 'escalate_to_human',
            'reason' => 'Silence',
        ]);

        // A second package is stored for this invoice between the guard and the insert. It writes on
        // pgsql_migrate, a separate session, so it commits. RLS is FORCED and constrains the owner role too.
        $racer = DB::connection('pgsql_migrate');
        $racer->statement("SET app.business_id = '{$biz->id}'");

        ArCollectionsPackage::creating(function () use ($racer, $biz, $invoice): void {
            $racer->table('ar_collections_packages')->insert([
                'business_id' => $biz->id,
                'invoice_id' => $invoice->id,
                'contents' => json_encode(['invoice_number' => 'INV-PKG-RACE']),
            ]);
        });

        $refused = null;
        try {
            $this->engine->packageForCollections($biz->id, $invoice->id, $user->id);
        } catch (AlreadyPackagedException $e) {
            $refused = $e;
        } finally {
            ArCollectionsPackage::flushEventListeners();
        }

        // The invoice keeps one package.
        $this->assertSame(
            1,
            ArCollectionsPackage::where('business_id', $biz->id)->where('invoice_id', $invoice->id)->count()
        );

        // The loser is refused exactly as the guard refuses a second package.
        $this->assertNotNull($refused);
        $this->assertStringContainsString(
            'INV-PKG-RACE has already been packaged for collections',
            $refused->getMessage()
        );
    }

    public function test_dunning_history_lists_newest_first(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Dunning Hist Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'K', 'last_name' => 'K']);
        $invoice = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-DUN',
            'total_cents' => 60000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(15)->toDateString(),
        ]);

        $recordAction = new ArRecordReasonAction(app(ArEngine::class));
        $recordAction->handle($biz->id, $invoice->id, 'silence');

        sleep(1);

        $recordAction->handle($biz->id, $invoice->id, 'promised');

        $historyAction = new ArDunningHistoryAction;
        $history = $historyAction->handle($biz->id, $invoice->id);

        $this->assertCount(2, $history);
        $this->assertEquals('promised', $history[0]['reason_code']);
        $this->assertEquals('silence', $history[1]['reason_code']);
    }
}
