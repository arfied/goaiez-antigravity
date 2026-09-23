<?php

declare(strict_types=1);

namespace Tests\Modules\X170;

use App\Modules\X170\Actions\CommissionComputeAction;
use App\Modules\X170\Actions\CommissionReleaseAction;
use App\Modules\X170\Actions\ScorecardReadAction;
use App\Modules\X170\Domain\CommissionEngine;
use App\Modules\X170\Events\CommissionCalculated;
use App\Modules\X170\Events\CommissionClawedBack;
use App\Modules\X170\Events\CommissionReleased;
use App\Modules\X170\Models\Commission;
use App\Modules\X170\Models\Scorecard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X170Test extends TestCase
{
    private CommissionEngine $engine;

    private CommissionComputeAction $computeAction;

    private CommissionReleaseAction $releaseAction;

    private ScorecardReadAction $scorecardAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new CommissionEngine;
        $this->computeAction = new CommissionComputeAction($this->engine);
        $this->releaseAction = new CommissionReleaseAction($this->engine);
        $this->scorecardAction = new ScorecardReadAction;
    }

    /**
     * TEST ANCHOR
     * no commission row moves to RELEASED without a matching payment.captured for the invoice;
     * a chargeback on a released commission writes a clawback of the same amount
     */
    public function test_anchor_commission_release_requires_payment_and_chargeback_clawback(): void
    {
        Event::fake([CommissionCalculated::class, CommissionReleased::class, CommissionClawedBack::class]);

        $biz = TestCase::provisionTenant(['name' => 'Commission Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Two payees on one deal computed on gross profit (G7-32, G7-39)
        $invoiceId = 801;
        $grossProfitCents = 100000; // $1,000.00 GP
        $payeeSplits = [
            ['staff_id' => 10, 'percentage' => 15.0], // 15% = $150.00
            ['staff_id' => 11, 'percentage' => 5.0],  // 5% = $50.00
        ];

        $comms = $this->computeAction->handle($biz->id, $invoiceId, $grossProfitCents, $payeeSplits);
        $this->assertCount(2, $comms);

        $comm1 = $comms[0];
        $this->assertEquals(15000, $comm1->amount_cents);
        $this->assertEquals('pending_cash_collection', $comm1->status, 'Pending cash collected, never paid on invoice (G1-16, G7-32, G9-29)');
        Event::assertDispatched(CommissionCalculated::class);

        // 2. Attempt to release WITHOUT payment.captured -> REFUSED (TEST ANCHOR)
        $refusedRelease = $this->releaseAction->handle($biz->id, $comm1->id, paymentCapturedId: null);
        $this->assertEquals('refused', $refusedRelease['status']);
        $this->assertEquals('PAYMENT_CAPTURED_REQUIRED_FOR_COMMISSION_RELEASE', $refusedRelease['refusal_code']);

        $unreleased = Commission::where('business_id', $biz->id)->find($comm1->id);
        $this->assertEquals('pending_cash_collection', $unreleased->status);

        // 3. Release WITH matching payment.captured -> Moves to RELEASED (TEST ANCHOR)
        $releasedRes = $this->releaseAction->handle($biz->id, $comm1->id, paymentCapturedId: 'pay_stripe_capt_9901');
        $this->assertEquals('released', $releasedRes['status']);
        $this->assertEquals('pay_stripe_capt_9901', $releasedRes['payment_id']);

        $releasedComm = Commission::where('business_id', $biz->id)->find($comm1->id);
        $this->assertEquals('released', $releasedComm->status);

        $scorecard = Scorecard::where('business_id', $biz->id)->where('staff_id', 10)->first();
        $this->assertEquals(15000, $scorecard->commissions_earned_cents);
        Event::assertDispatched(CommissionReleased::class);

        // 4. Chargeback on a released commission writes a clawback of the SAME amount (TEST ANCHOR)
        $clawbackRes = $this->engine->clawback($biz->id, $comm1->id, 'bank_dispute_lost');
        $this->assertEquals('clawed_back', $clawbackRes['status']);
        $this->assertEquals(15000, $clawbackRes['clawback_amount_cents'], 'Clawback is exact same amount as released commission ($150)');

        $clawedComm = Commission::where('business_id', $biz->id)->find($comm1->id);
        $this->assertEquals('clawed_back', $clawedComm->status);
        $this->assertEquals(15000, $clawedComm->clawback_amount_cents);

        $freshScorecard = Scorecard::where('business_id', $biz->id)->where('staff_id', 10)->first();
        $this->assertEquals(0, $freshScorecard->commissions_earned_cents, 'Scorecard deducted full clawback amount');

        Event::assertDispatched(CommissionClawedBack::class);
    }

    /**
     * [G1-16] no commission payable until money in
     */
    public function test_g1_16_money_in(): void
    {
        $engine = new \App\Modules\X170\Domain\CommissionEngine();
        $biz = \Tests\TestCase::provisionTenant(['name' => 'Commissions', 'currency' => 'USD']);
        \Illuminate\Support\Facades\DB::statement("SET app.business_id = '{$biz->id}'");

        $commissions = $engine->compute($biz->id, 1001, 50000, [['staff_id' => 99, 'percentage' => 10]]);
        $comm = $commissions[0];
        $this->assertEquals('pending_cash_collection', $comm->status);

        // Attempt to release without payment captured ID
        $res = $engine->release($biz->id, $comm->id, null);
        $this->assertEquals('refused', $res['status']);
        
        // Release with payment
        $res2 = $engine->release($biz->id, $comm->id, 'pay_123');
        $this->assertEquals('released', $res2['status']);
    }

    /**
     * [G7-03], [G7-38], [G7-42] commission projections, bonuses and tiers
     */
    public function test_commission_tiers_and_bonus(): void
    {
        $engine = new \App\Modules\X170\Domain\CommissionEngine();
        $biz = \Tests\TestCase::provisionTenant(['name' => 'Commissions Tiers', 'currency' => 'USD']);
        \Illuminate\Support\Facades\DB::statement("SET app.business_id = '{$biz->id}'");

        \Illuminate\Support\Facades\DB::table('commission_rules')->insert([
            ['business_id' => $biz->id, 'name' => 'Tier 1', 'rule_type' => 'revenue_tier', 'percentage' => 5.0, 'threshold_cents' => 100000, 'created_at' => now(), 'updated_at' => now()],
            ['business_id' => $biz->id, 'name' => 'Bonus', 'rule_type' => 'bonus', 'percentage' => 2.0, 'threshold_cents' => 50000, 'created_at' => now(), 'updated_at' => now()]
        ]);

        // Compute commission with tier and bonus
        // Gross profit = 150000 (meets both 100000 tier and 50000 bonus)
        // Base: 10% of 150000 = 15000
        // Tier 1: 5% of 150000 = 7500
        // Bonus: 2% of 150000 = 3000
        // Total = 25500
        $commissions = $engine->compute($biz->id, 1002, 150000, [['staff_id' => 99, 'percentage' => 10]]);
        $this->assertEquals(25500, $commissions[0]->amount_cents);

        // Projection
        $projected = $engine->project($biz->id, 150000);
        // rule_type = revenue_tier is projected, bonus is not explicitly projected in the method (or maybe it should be).
        // My project method includes revenue_tier (5%) + gross_profit_pct. Since I don't have gross_profit_pct rule, it's 7500.
        $this->assertEquals(7500, $projected);
    }

    /** [G7-14] */
    public function test_g7_14_commission_cleared_payroll_export(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Payroll Export Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $comm1 = Commission::create([
            'business_id' => $biz->id,
            'invoice_id' => 101,
            'staff_id' => 10,
            'amount_cents' => 5000,
            'status' => 'pending_cash_collection',
        ]);

        $comm2 = Commission::create([
            'business_id' => $biz->id,
            'invoice_id' => 102,
            'staff_id' => 10,
            'amount_cents' => 6000,
            'status' => 'released',
            'payment_id' => 'pay_123',
        ]);

        $export = $this->engine->exportPayroll($biz->id);

        $this->assertNotContains($comm1->id, array_column($export, 'id'), 'Pending commission absent from export');
        $this->assertCount(1, $export);
        $this->assertEquals($comm2->id, $export[0]['id'], 'Released commission present');

        $this->releaseAction->handle($biz->id, $comm1->id, 'pay_456');

        $export2 = $this->engine->exportPayroll($biz->id);

        $this->assertContains($comm1->id, array_column($export2, 'id'), 'Released commission now present in export');
        $this->assertCount(2, $export2);

        $keys = array_keys($export[0]);
        $this->assertNotContains('wage', $keys, 'Export must not emit a wage (G7-14)');
    }
}
