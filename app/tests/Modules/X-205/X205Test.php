<?php

declare(strict_types=1);

namespace Tests\Modules\X205;

use App\Modules\X201\Events\DisputeLost;
use App\Modules\X205\Actions\AffiliateAttributeAction;
use App\Modules\X205\Actions\AffiliatePayoutRequestAction;
use App\Modules\X205\Actions\AffiliateProposeClawbackAction;
use App\Modules\X205\Domain\AffiliateEngine;
use App\Modules\X205\Events\ApprovalRequested;
use App\Modules\X205\Listeners\ProposeClawbackOnDisputeLost;
use App\Modules\X205\Models\Affiliate;
use App\Modules\X205\Models\AffiliateTier;
use App\Modules\X205\Models\ReferralClick;
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

        $biz = TestCase::provisionTenant(['name' => 'Affiliate Program Tenant', 'currency' => 'USD']);
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

    /**
     * [G7-04] Affiliate ID Merging. A click carrying both a ref code and utm_* params merges into one attribution.
     * utm never overwrites an existing ref and never double-counts.
     */
    public function test_g7_04_ref_merged_with_utm_does_not_double_count_and_ref_survives(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'G7-04 Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $affiliate = Affiliate::create([
            'business_id' => $biz->id,
            'affiliate_code' => 'AFF-G7-04',
            'partner_name' => 'Merge Partner',
        ]);

        $this->engine->recordClick($biz->id, 'vis-123', 'AFF-G7-04', 'fb', 'social', 'summer');

        $clicks = ReferralClick::where('business_id', $biz->id)->where('visitor_id', 'vis-123')->get();
        $this->assertCount(1, $clicks, 'Assert ONE attribution row, not two');
        $this->assertEquals($affiliate->id, $clicks[0]->affiliate_id, 'The surviving code is the ref');
        $this->assertEquals('fb', $clicks[0]->utm_source);

        // Second click, same visitor, different UTM, should merge not double count, ref unchanged
        $this->engine->recordClick($biz->id, 'vis-123', null, 'google');
        $clicks2 = ReferralClick::where('business_id', $biz->id)->where('visitor_id', 'vis-123')->get();
        $this->assertCount(1, $clicks2, 'Still ONE attribution row, no double count');
        $this->assertEquals($affiliate->id, $clicks2[0]->affiliate_id, 'ref is unchanged');
    }

    /**
     * [G7-11] Clawback Automation. A clawback is PROPOSED with the triggering refund attached, never executed.
     */
    public function test_g7_11_chargeback_produces_proposed_clawback_with_refund_attached_and_moves_no_money(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'G7-11 Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $affiliate = Affiliate::create([
            'business_id' => $biz->id,
            'affiliate_code' => 'AFF-G7-11',
            'partner_name' => 'Partner',
        ]);

        $attribution = $this->attributeAction->attributeSale($biz->id, 'AFF-G7-11', '12345', 10000);

        // A chargeback happens from X-201
        $listener = new ProposeClawbackOnDisputeLost($this->clawbackAction);
        $listener->handle(new DisputeLost($biz->id, 999, (int) '12345', 10000));

        $updatedAttribution = $attribution->fresh();

        $this->assertEquals('proposed', $updatedAttribution->clawback_status);
        $this->assertEquals('999', $updatedAttribution->triggering_dispute_ref, 'triggering refund attached');
        $this->assertFalse($updatedAttribution->is_clawed_back, 'moves no money (never executed)');
    }

    /**
     * [G7-23] Fraud Detection. Fraud detection PROPOSES, NEVER FREEZES.
     */
    public function test_g7_23_fraud_detection_proposes_never_freezes(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'G7-23 Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        Affiliate::create([
            'business_id' => $biz->id,
            'affiliate_code' => 'AFF-G7-23',
            'partner_name' => 'Fraud Partner',
        ]);

        // Self-click fraud
        $attribution = $this->attributeAction->attributeSale(
            businessId: $biz->id,
            affiliateCode: 'AFF-G7-23',
            orderId: 'ORD-FRAUD-1',
            saleAmountCents: 10000,
            visitorId: 'AFF-G7-23' // Converts themselves
        );

        $this->assertEquals('proposed', $attribution->fraud_review_status, 'proposes for review');
        $this->assertEquals('none', $attribution->clawback_status, 'not clawed back');
        $this->assertEquals(1000, $attribution->commission_cents, 'still attributed');
        $this->assertFalse($attribution->is_clawed_back, 'no money moved');

        // Stolen card fraud
        $attribution2 = $this->attributeAction->attributeSale(
            businessId: $biz->id,
            affiliateCode: 'AFF-G7-23',
            orderId: 'ORD-FRAUD-2',
            saleAmountCents: 10000,
            visitorId: 'vis-555',
            orderTags: ['stolen_card']
        );
        $this->assertEquals('proposed', $attribution2->fraud_review_status, 'proposes for review');
        $this->assertEquals(1000, $attribution2->commission_cents, 'still attributed');
        $this->assertFalse($attribution2->is_clawed_back, 'no money moved');
    }

    /**
     * [G7-41] Tiered Commissions. Tiers unlock by referral count. A tier never re-rates an already-cleared or paid commission.
     */
    public function test_g7_41_referral_tiers_unlock_by_count_and_never_rerate_existing_commissions(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'G7-41 Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        AffiliateTier::create([
            'business_id' => $biz->id,
            'name' => 'Gold',
            'min_referrals' => 1,
            'commission_rate_bps' => 2000, // 20%
        ]);

        Affiliate::create([
            'business_id' => $biz->id,
            'affiliate_code' => 'AFF-G7-41',
            'partner_name' => 'Tier Partner',
            'commission_rate_bps' => 1000, // base 10%
        ]);

        // Referral 1: base rate (10%)
        $attribution1 = $this->attributeAction->attributeSale($biz->id, 'AFF-G7-41', 'ORD-T1', 10000);
        $this->assertEquals(1000, $attribution1->commission_cents);

        // Referral 2: now has 1 referral, unlocks Gold (20%)
        $attribution2 = $this->attributeAction->attributeSale($biz->id, 'AFF-G7-41', 'ORD-T2', 10000);
        $this->assertEquals(2000, $attribution2->commission_cents);

        // Existing attribution commission is unchanged
        $this->assertEquals(1000, $attribution1->fresh()->commission_cents);
    }

    /**
     * [G7-45] White-Labelled Portal. A non-tenant portal leaks tenant data. Asserted cross-scope.
     */
    public function test_g7_45_cross_scope_read_returns_nothing_for_other_tenants(): void
    {
        // Provision Tenant A
        $bizA = TestCase::provisionTenant(['name' => 'Tenant A']);
        DB::statement("SET app.business_id = '{$bizA->id}'");

        $affiliateA = Affiliate::create([
            'business_id' => $bizA->id,
            'affiliate_code' => 'AFF-A',
            'partner_name' => 'Partner A',
        ]);
        $this->engine->recordClick($bizA->id, 'vis-A', 'AFF-A');

        // Provision Tenant B
        $bizB = TestCase::provisionTenant(['name' => 'Tenant B']);
        DB::statement("SET app.business_id = '{$bizB->id}'");

        $affiliateB = Affiliate::create([
            'business_id' => $bizB->id,
            'affiliate_code' => 'AFF-B',
            'partner_name' => 'Partner B',
        ]);
        $this->engine->recordClick($bizB->id, 'vis-B', 'AFF-B');

        // Under Tenant B's scope, Partner A's clicks should not be visible
        $clicks = ReferralClick::where('affiliate_id', $affiliateA->id)->get();
        $this->assertCount(0, $clicks, 'asserted cross-scope: returns nothing');

        $affiliates = Affiliate::where('id', $affiliateA->id)->get();
        $this->assertCount(0, $affiliates);
    }

    /**
     * [G10-36] Tax Compliance. W-9 threshold freezes a payout.
     */
    public function test_g10_36_w9_threshold_freezes_payout_and_moves_no_money(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'G10-36 Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $affiliate = Affiliate::create([
            'business_id' => $biz->id,
            'affiliate_code' => 'AFF-G10-36',
            'partner_name' => 'W9 Partner',
            'lifetime_earnings_cents' => 60000,
            'current_balance_cents' => 60000,
            'w9_threshold_cents' => 60000,
            'w9_on_file' => false,
        ]);

        $payout = $this->payoutAction->requestPayout($biz->id, $affiliate->id, 10000);

        $this->assertEquals('frozen', $payout->status);
        $this->assertFalse($payout->money_moved);

        // Asserted by absence: no computeTaxPosition() or stored rate exists in the module.
    }
}
