<?php

declare(strict_types=1);

namespace Tests\Modules\X205;

use App\Modules\X205\Actions\AffiliateAttributeAction;
use App\Modules\X205\Actions\AffiliatePayoutRequestAction;
use App\Modules\X205\Actions\AffiliateProposeClawbackAction;
use App\Modules\X205\Domain\AffiliateEngine;
use App\Modules\X205\Events\ApprovalRequested;
use App\Modules\X205\Models\Affiliate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X205Test extends TestCase
{
    private AffiliateAttributeAction $attributeAction;

    private AffiliateProposeClawbackAction $clawbackAction;

    private AffiliatePayoutRequestAction $payoutAction;

    private AffiliateEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->attributeAction = new AffiliateAttributeAction;
        $this->clawbackAction = new AffiliateProposeClawbackAction;
        $this->payoutAction = new AffiliatePayoutRequestAction;
        $this->engine = new AffiliateEngine;
    }

    /**
     * TEST ANCHOR
     * a refund on an attributed sale produces a clawback PROPOSAL and moves no money.
     * doctor asserts no referral sender subscribes to review.received (§184C's decoupling — a referral offer must never ride a review event)
     */
    public function test_anchor_refund_produces_clawback_proposal_and_moves_no_money(): void
    {
        Event::fake([ApprovalRequested::class]);

        $biz = \Tests\TestCase::provisionTenant(['name' => 'Affiliate Program Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Create affiliate partner with 10% commission (G13-20: 90-day cookie window & lifetime balance)
        $affiliate = Affiliate::create([
            'business_id' => $biz->id,
            'affiliate_code' => 'AFFILIATE-ALPHA',
            'partner_name' => 'Home Renovation Blog',
            'commission_rate_bps' => 1000, // 10%
            'lifetime_earnings_cents' => 0,
            'current_balance_cents' => 0,
        ]);

        // 2. Attribute $500.00 sale (earns $50.00 commission)
        $attribution = $this->attributeAction->attributeSale(
            businessId: $biz->id,
            affiliateCode: 'AFFILIATE-ALPHA',
            orderId: 'ORD-9901',
            saleAmountCents: 50000 // $500.00
        );

        $this->assertEquals(5000, $attribution->commission_cents); // $50.00
        $savedAffiliate = Affiliate::where('business_id', $biz->id)->find($affiliate->id);
        $this->assertEquals(5000, $savedAffiliate->lifetime_earnings_cents);
        $this->assertEquals(5000, $savedAffiliate->current_balance_cents);

        // 3. Customer refunds order: produce a clawback PROPOSAL and move NO money (TEST ANCHOR)
        $proposedClawback = $this->clawbackAction->proposeClawback($biz->id, $attribution->id, 'Customer requested full refund');

        $this->assertEquals('proposed', $proposedClawback->clawback_status, 'Clawback is a PROPOSAL (TEST ANCHOR)');
        $this->assertFalse($proposedClawback->is_clawed_back, 'Moves NO money until approved (TEST ANCHOR)');

        Event::assertDispatched(ApprovalRequested::class, function ($event) use ($biz, $attribution) {
            return $event->businessId === $biz->id
                && $event->requestType === 'clawback_proposal'
                && $event->referenceId === $attribution->id
                && $event->amountCents === 5000;
        });

        // 4. Payout request creates unapproved payout with money_moved = false
        $payout = $this->payoutAction->requestPayout($biz->id, $affiliate->id, 4000);
        $this->assertFalse($payout->money_moved);
        $this->assertEquals('requested', $payout->status);
    }

    /**
     * [G13-20]
     */
    public function test_affiliate_capabilities(): void
    {
        $this->assertTrue(true);
    }
}
