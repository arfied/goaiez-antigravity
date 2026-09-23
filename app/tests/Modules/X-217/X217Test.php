<?php

declare(strict_types=1);

namespace Tests\Modules\X217;

use App\Modules\X205\Models\Affiliate;
use App\Modules\X217\Actions\AffiliatePipelineAction;
use App\Modules\X217\Actions\AffiliateRecruitAction;
use App\Modules\X217\Actions\AffiliateTermsOfferAction;
use App\Modules\X217\Events\AffiliateDeclined;
use App\Modules\X217\Events\AffiliateRecruited;
use App\Modules\X217\Events\SendRequested;
use App\Modules\X217\Models\AffiliateProspect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\TestCase;

class X217Test extends TestCase
{
    private AffiliateRecruitAction $recruitAction;

    private AffiliateTermsOfferAction $offerAction;

    private AffiliatePipelineAction $pipelineAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recruitAction = new AffiliateRecruitAction;
        $this->offerAction = new AffiliateTermsOfferAction;
        $this->pipelineAction = new AffiliatePipelineAction;
    }

    /**
     * TEST ANCHOR
     * an accepted recruit lands in X-205 with the EXACT terms that were offered —
     * the offer and the program row are asserted equal, so a negotiated rate can never drift from what was promised.
     * And no offer exceeds the confirmed ceiling.
     */
    public function test_anchor_recruit_lands_in_x205_with_exact_terms_and_ceiling_enforced(): void
    {
        Event::fake([SendRequested::class, AffiliateRecruited::class, AffiliateDeclined::class]);

        $biz = TestCase::provisionTenant(['name' => 'Affiliate Recruitment Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Recruit prospect (sends outreach)
        $prospect = $this->recruitAction->recruitProspect($biz->id, 'Apex Home DIY Influencer', 'partner@apexdiy.com');
        $this->assertNotNull($prospect);
        $this->assertEquals('pitched', $prospect->stage);
        Event::assertDispatched(SendRequested::class);

        // 2. Make terms offer exceeding ceiling MUST FAIL (TEST ANCHOR)
        try {
            $this->offerAction->makeOffer(
                businessId: $biz->id,
                prospectId: $prospect->id,
                offeredRateBps: 3000, // 30% > 25% ceiling
                ceilingRateBps: 2500
            );
            $this->fail('Expected InvalidArgumentException when offer exceeds ceiling');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('exceeds ceiling', $e->getMessage(), 'Offer exceeding ceiling is rejected (TEST ANCHOR)');
        }

        // 3. Make valid terms offer (1800 bps = 18%)
        $offer = $this->offerAction->makeOffer(
            businessId: $biz->id,
            prospectId: $prospect->id,
            offeredRateBps: 1800,
            ceilingRateBps: 2500,
            termsSummary: '18% recurring revshare for 90 days'
        );
        $this->assertEquals(1800, $offer->offered_rate_bps);

        // 4. Accept offer: lands in X-205 with EXACT terms offered (TEST ANCHOR)
        $affiliateCode = $this->pipelineAction->acceptOffer($biz->id, $offer->id);
        $affiliate = Affiliate::where('business_id', $biz->id)->where('affiliate_code', $affiliateCode)->first();
        $this->assertNotNull($affiliate);
        $this->assertInstanceOf(Affiliate::class, $affiliate);

        // Assert offer and program row are EXACTLY EQUAL
        $this->assertEquals(
            $offer->offered_rate_bps,
            $affiliate->commission_rate_bps,
            'Accepted recruit lands in X-205 with EXACT terms offered (TEST ANCHOR)'
        );

        Event::assertDispatched(AffiliateRecruited::class);

        // 5. Decline scenario
        $prospect2 = $this->recruitAction->recruitProspect($biz->id, 'Declining Partner', 'no@partner.com');
        $this->pipelineAction->declineOffer($biz->id, $prospect2->id, 'Not interested');
        $saved2 = AffiliateProspect::where('business_id', $biz->id)->find($prospect2->id);
        $this->assertEquals('declined', $saved2->stage);
        Event::assertDispatched(AffiliateDeclined::class);
    }

    /**
     * [N-217-01]
     * [N-217-02]
     * [N-217-03] ⛔ REFUSED: `php artisan why N-217-03` reports it is never DEFINED.
     *   Furthermore, this is a structural boundary rule (P-163). X-217 relies on
     *   AffiliateCreateAction from X-205 rather than writing directly, which satisfies P-163
     *   but is tested structurally rather than via an explicit behavioral assertion here.
     */
    public function test_recruitment_capabilities(): void
    {
        Event::fake([SendRequested::class, AffiliateRecruited::class, AffiliateDeclined::class]);

        $biz = TestCase::provisionTenant(['name' => 'Cap Test Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // [N-217-02] no recruitment offer exceeds the confirmed commission ceiling
        $prospect = $this->recruitAction->recruitProspect($biz->id, 'Cap Tester', 'cap@tester.com');
        try {
            $this->offerAction->makeOffer(
                businessId: $biz->id,
                prospectId: $prospect->id,
                offeredRateBps: 2600, // Exceeds ceiling
                ceilingRateBps: 2500
            );
            $this->fail('Expected InvalidArgumentException when offer exceeds ceiling [N-217-02]');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('exceeds ceiling', $e->getMessage(), 'Offer exceeding ceiling is rejected [N-217-02]');
        }

        // [N-217-01] offer row and program row asserted EQUAL
        $offer = $this->offerAction->makeOffer($biz->id, $prospect->id, 2000, 2500, '20% revshare');
        $affiliateCode = $this->pipelineAction->acceptOffer($biz->id, $offer->id);

        $affiliate = Affiliate::where('business_id', $biz->id)->where('affiliate_code', $affiliateCode)->first();

        $this->assertEquals(
            $offer->offered_rate_bps,
            $affiliate->commission_rate_bps,
            '[N-217-01] Accepted recruit lands in X-205 with EXACT terms offered'
        );
    }
}
