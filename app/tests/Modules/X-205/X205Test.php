<?php

declare(strict_types=1);

namespace Tests\Modules\X205;

use App\Modules\X201\Events\DisputeLost;
use App\Modules\X205\Actions\AffiliateAttributeAction;
use App\Modules\X205\Actions\AffiliatePayoutRequestAction;
use App\Modules\X205\Actions\AffiliateProposeClawbackAction;
use App\Modules\X205\Domain\AffiliateEngine;
use App\Modules\X205\Domain\SaleAttributionRefused;
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
        $this->engine = app(AffiliateEngine::class);
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
     * [G7-04], [G7-11], [G7-23], [G7-41], [G7-45], [G10-36], [G13-20]
     */
    public function test_g13_20_sale_outside_the_90_day_cookie_is_refused_and_lifetime_balance_is_a_running_sum(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'G13-20 Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $affiliate = Affiliate::create([
            'business_id' => $biz->id,
            'affiliate_code' => 'AFF-G13',
            'partner_name' => 'Cookie Partner',
            'commission_rate_bps' => 1000, // 10%
            'lifetime_earnings_cents' => 0,
            'current_balance_cents' => 0,
        ]);

        $this->engine->recordClick($biz->id, 'vis-fresh', 'AFF-G13');

        $staleClick = $this->engine->recordClick($biz->id, 'vis-stale', 'AFF-G13');
        $staleClick->created_at = now()->subDays(91);
        $staleClick->save();

        $this->attributeAction->attributeSale($biz->id, 'AFF-G13', 'ORD-F1', 10000, 'vis-fresh');
        $this->attributeAction->attributeSale($biz->id, 'AFF-G13', 'ORD-F2', 20000, 'vis-fresh');

        $affiliate->refresh();
        $this->assertEquals(3000, $affiliate->lifetime_earnings_cents);
        $this->assertEquals(3000, $affiliate->current_balance_cents);

        try {
            $this->attributeAction->attributeSale($biz->id, 'AFF-G13', 'ORD-S1', 15000, 'vis-stale');
            $this->fail('Expected SaleAttributionRefused exception');
        } catch (SaleAttributionRefused $e) {
            $this->assertEquals('Sale outside 90-day cookie or missing click', $e->getMessage());
        }

        $this->assertDatabaseMissing('affiliate_attributions', [
            'business_id' => $biz->id,
            'order_id' => 'ORD-S1',
        ]);

        $affiliate->refresh();
        $this->assertEquals(3000, $affiliate->lifetime_earnings_cents);
        $this->assertEquals(3000, $affiliate->current_balance_cents);
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
        $affiliate->refresh();
        $lifetimeBefore = $affiliate->lifetime_earnings_cents;
        $balanceBefore = $affiliate->current_balance_cents;

        // A chargeback happens from X-201
        $listener = new ProposeClawbackOnDisputeLost($this->clawbackAction);
        $listener->handle(new DisputeLost($biz->id, 999, (int) '12345', 10000));

        $updatedAttribution = $attribution->fresh();
        $affiliate->refresh();

        $this->assertEquals('proposed', $updatedAttribution->clawback_status);
        $this->assertEquals('999', $updatedAttribution->triggering_dispute_ref, 'triggering refund attached');
        $this->assertFalse($updatedAttribution->is_clawed_back, 'moves no money (never executed)');
        $this->assertEquals($lifetimeBefore, $affiliate->lifetime_earnings_cents, 'lifetime earnings unchanged');
        $this->assertEquals($balanceBefore, $affiliate->current_balance_cents, 'current balance unchanged');
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

        // Honest referral
        $this->engine->recordClick($biz->id, 'vis-honest', 'AFF-G7-23');

        $attribution = $this->attributeAction->attributeSale(
            businessId: $biz->id,
            affiliateCode: 'AFF-G7-23',
            orderId: 'ORD-HONEST-1',
            saleAmountCents: 10000,
            visitorId: 'vis-honest'
        );

        $this->assertEquals('none', $attribution->fraud_review_status, 'honest referral is clean');
        $this->assertEquals('none', $attribution->clawback_status, 'not clawed back');
        $this->assertEquals(1000, $attribution->commission_cents, 'still attributed');
        $this->assertFalse($attribution->is_clawed_back, 'no money moved');

        $payout = $this->payoutAction->requestPayout($biz->id, $attribution->affiliate_id, 1000);
        $this->assertEquals('requested', $payout->status, 'proposes, never freezes');

        // The fraud is the stolen card, not a missing click: G13-20 refuses a
        // sale with no referral click, so the fraud scenario records one first.
        $this->engine->recordClick($biz->id, 'vis-555', 'AFF-G7-23');

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

        // Attribute a real sale to Partner A
        $this->attributeAction->attributeSale($bizA->id, 'AFF-A', 'ORD-A', 10000, 'vis-A');

        // Provision Tenant B
        $bizB = TestCase::provisionTenant(['name' => 'Tenant B']);
        DB::statement("SET app.business_id = '{$bizB->id}'");

        $affiliateB = Affiliate::create([
            'business_id' => $bizB->id,
            'affiliate_code' => 'AFF-B',
            'partner_name' => 'Partner B',
        ]);
        $this->engine->recordClick($bizB->id, 'vis-B', 'AFF-B');

        // Under Tenant A's scope, Partner A sees their own rows
        DB::statement("SET app.business_id = '{$bizA->id}'");
        $dataA = $this->engine->getPartnerData($bizA->id, $affiliateA->id);
        $this->assertCount(1, $dataA['clicks'], 'asserted cross-scope: sees own rows');
        $this->assertEquals(1000, $dataA['pending_earnings'], 'tenant A sees actual pending earnings');

        // Under Tenant B's scope, Partner A's clicks should not be visible
        DB::statement("SET app.business_id = '{$bizB->id}'");
        $dataB = $this->engine->getPartnerData($bizB->id, $affiliateA->id);
        $this->assertCount(0, $dataB['clicks'], 'asserted cross-scope: returns nothing');
        $this->assertEquals(0, $dataB['pending_earnings'], 'tenant B sees 0 pending earnings');
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

        Event::fake([ApprovalRequested::class]);

        $payout = $this->payoutAction->requestPayout($biz->id, $affiliate->id, 10000);

        $this->assertEquals('frozen', $payout->status);
        $this->assertFalse($payout->money_moved);

        Event::assertDispatched(ApprovalRequested::class, function ($event) {
            return $event->status === 'frozen';
        });

        $moduleDir = base_path('app/Modules/X-205');
        $this->assertDirectoryExists($moduleDir);
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($moduleDir));
        $taxKeywords = ['tax_rate', 'tax_amount', 'taxOwed', 'computeTax', 'withholding_rate'];
        $found = [];

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());
                foreach ($taxKeywords as $keyword) {
                    if (stripos($content, $keyword) !== false) {
                        $found[] = $file->getFilename().':'.$keyword;
                    }
                }
            }
        }

        $this->assertEmpty($found, 'No computeTaxPosition() or stored rate exists in the module: '.implode(', ', $found));
    }

    /**
     * (R245) Affiliate code is composite unique with business_id
     */
    public function test_s13_affiliate_code_is_composite_unique_with_business_id(): void
    {
        $bizA = TestCase::provisionTenant(['name' => 'Tenant A']);
        $bizB = TestCase::provisionTenant(['name' => 'Tenant B']);

        DB::statement("SET app.business_id = '{$bizA->id}'");
        Affiliate::create([
            'business_id' => $bizA->id,
            'affiliate_code' => 'SHARED-CODE',
            'partner_name' => 'Partner A',
        ]);

        DB::statement("SET app.business_id = '{$bizB->id}'");
        Affiliate::create([
            'business_id' => $bizB->id,
            'affiliate_code' => 'SHARED-CODE',
            'partner_name' => 'Partner B',
        ]);

        DB::statement("SET app.business_id = '{$bizA->id}'");
        $resultsA = Affiliate::where('business_id', $bizA->id)->where('affiliate_code', 'SHARED-CODE')->get();
        $this->assertCount(1, $resultsA);

        DB::statement("SET app.business_id = '{$bizB->id}'");
        $resultsB = Affiliate::where('business_id', $bizB->id)->where('affiliate_code', 'SHARED-CODE')->get();
        $this->assertCount(1, $resultsB);

        $this->assertNotEquals($resultsA->first()->id, $resultsB->first()->id);
    }
}
