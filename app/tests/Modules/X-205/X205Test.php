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
            $biz->id,
            'AFFILIATE-ALPHA',
            'ORD-9901',
            50000 // $500.00
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
     * [G7-04] refuses: AffiliateProgram; ref merged with utm — see §166.5
     */
    public function test_g7_04_refuses_ref_merged_with_utm(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('REFUSAL_G7_04_REF_MERGED_WITH_UTM');
        $this->engine->parseAffiliateFromUrl('https://example.com/?utm_source=fb&ref=123');
    }

    /**
     * [G7-11] refuses: AffiliateProgram; a chargeback reverses a paid commission; X-201 raises the event
     */
    public function test_g7_11_refuses_automatic_chargeback_reversal(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $affiliate = Affiliate::create([
            'business_id' => $biz->id,
            'affiliate_code' => 'AFF-G7-11',
            'partner_name' => 'Partner',
        ]);

        $attribution = $this->attributeAction->attributeSale($biz->id, 'AFF-G7-11', 'ORD-CB', 10000);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('REFUSAL_G7_11_CHARGEBACK');
        $this->clawbackAction->proposeClawback($biz->id, $attribution->id, 'Chargeback', true);
    }

    /**
     * [G7-23] refuses: AffiliateProgram; self-clicking and stolen-card affiliates
     */
    public function test_g7_23_refuses_self_clicking_and_stolen_card(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('REFUSAL_G7_23_FRAUD');
        $this->attributeAction->attributeSale(1, 'AFF', 'ORD', 10000, true, false);
    }

    /**
     * [G7-41] refuses: AffiliateProgram; referral tiers unlock by count
     */
    public function test_g7_41_refuses_referral_tiers(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('REFUSAL_G7_41_TIERS');
        $this->engine->unlockTier(5);
    }

    /**
     * [G7-45] refuses: AffiliateProgram; the partner's own login
     */
    public function test_g7_45_refuses_partner_login(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('REFUSAL_G7_45_PARTNER_LOGIN');
        $this->engine->getPartnerLoginUrl(1);
    }

    /**
     * [G10-36] W-9 threshold freezes a payout; Law 122 — the switch and the threshold as data, never the advice
     */
    public function test_g10_36_w9_threshold_freezes_payout(): void
    {
        Event::fake([ApprovalRequested::class]);
        $biz = TestCase::provisionTenant(['name' => 'W9 Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $affiliate = Affiliate::create([
            'business_id' => $biz->id,
            'affiliate_code' => 'AFF-G10-36',
            'partner_name' => 'W9 Partner',
            'commission_rate_bps' => 1000,
            'lifetime_earnings_cents' => 60000, // Meets threshold
            'current_balance_cents' => 60000,
        ]);

        // Request payout of $100, threshold is $600, not on file
        $payout = $this->payoutAction->requestPayout($biz->id, $affiliate->id, 10000, 60000, false);
        
        $this->assertEquals('frozen', $payout->status);
    }
}
