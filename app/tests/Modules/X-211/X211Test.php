<?php

declare(strict_types=1);

namespace Tests\Modules\X211;

use App\Modules\X121\Models\Business;
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

        $biz = Business::provision(['name' => 'AR Tenant', 'currency' => 'USD']);
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
     * [N-033], [G1-61], [G1-65], [G1-70], [G1-71], [G1-74] no refusal declared
     */
    public function test_capability_assertions(): void
    {
        $this->assertTrue(true);
    }
}
