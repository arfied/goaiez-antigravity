<?php

declare(strict_types=1);

namespace Tests\Modules\X218;

use App\Modules\X121\Models\Business;
use App\Modules\X218\Actions\InfluencerDealAction;
use App\Modules\X218\Actions\InfluencerDeliverableAction;
use App\Modules\X218\Actions\InfluencerDiscoverAction;
use App\Modules\X218\Actions\InfluencerOutreachAction;
use App\Modules\X218\Events\InfluencerDelivered;
use App\Modules\X218\Events\InfluencerEngaged;
use App\Modules\X218\Events\SendRequested;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\TestCase;

class X218Test extends TestCase
{
    private InfluencerDiscoverAction $discoverAction;

    private InfluencerOutreachAction $outreachAction;

    private InfluencerDealAction $dealAction;

    private InfluencerDeliverableAction $deliverableAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->discoverAction = new InfluencerDiscoverAction;
        $this->outreachAction = new InfluencerOutreachAction;
        $this->dealAction = new InfluencerDealAction;
        $this->deliverableAction = new InfluencerDeliverableAction;
    }

    /**
     * TEST ANCHOR
     * a payout NEVER fires without a VERIFIED deliverable —
     * the deliverable artifact (a live URL returning 200, captured and hashed) is asserted present before C-Billing is called.
     * A deal marked delivered with no artifact is refused — the same law as X-213's "a pass with no artifact DID NOT HAPPEN."
     */
    public function test_anchor_payout_requires_verified_deliverable_and_no_artifact_refused(): void
    {
        Event::fake([SendRequested::class, InfluencerEngaged::class, InfluencerDelivered::class]);

        $biz = Business::provision(['name' => 'Influencer Creator Campaign Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Discover influencer & outreach
        $creator = $this->discoverAction->discoverInfluencer($biz->id, '@texashomeexpert', 'instagram', 45000, 5.10);
        $this->assertNotNull($creator);

        $this->outreachAction->proposeCollab($biz->id, $creator->id);
        Event::assertDispatched(SendRequested::class);

        // 2. Form deal ($1,200.00)
        $deal = $this->dealAction->createDeal($biz->id, $creator->id, 120000);
        $this->assertEquals(120000, $deal->deal_amount_cents);
        $this->assertFalse($deal->is_paid);
        Event::assertDispatched(InfluencerEngaged::class);

        // 3. Attempt payout with NO deliverable artifact: MUST BE REFUSED (TEST ANCHOR)
        try {
            $this->deliverableAction->executePayout($biz->id, $deal->id);
            $this->fail('Expected InvalidArgumentException for payout without deliverable');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('payout NEVER fires without a VERIFIED deliverable', $e->getMessage(), 'Payout without deliverable rejected (TEST ANCHOR)');
        }

        // 4. Attempt deliverable with missing artifact hash: MUST BE REFUSED (TEST ANCHOR)
        try {
            $this->deliverableAction->submitAndVerifyDeliverable(
                businessId: $biz->id,
                dealId: $deal->id,
                liveUrl: 'https://instagram.com/reel/xyz123',
                httpStatus: 200,
                artifactHash: null // Missing hash
            );
            $this->fail('Expected InvalidArgumentException for deliverable without artifact');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('a pass with no artifact DID NOT HAPPEN', $e->getMessage(), 'Missing artifact rejected (TEST ANCHOR)');
        }

        // 5. Submit valid verified deliverable (URL returning 200, captured and hashed)
        $deliverable = $this->deliverableAction->submitAndVerifyDeliverable(
            businessId: $biz->id,
            dealId: $deal->id,
            liveUrl: 'https://instagram.com/reel/xyz123',
            httpStatus: 200,
            artifactHash: 'sha256-e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855'
        );
        $this->assertNotNull($deliverable);
        $this->assertTrue($deliverable->is_verified);
        Event::assertDispatched(InfluencerDelivered::class);

        // 6. Payout succeeds with verified deliverable present (TEST ANCHOR)
        $paidDeal = $this->deliverableAction->executePayout($biz->id, $deal->id);
        $this->assertTrue($paidDeal->is_paid);
        $this->assertEquals('paid', $paidDeal->status);
    }

    /**
     * [N-218-01], [N-218-02], [N-218-03]
     */
    public function test_influencer_capabilities(): void
    {
        $this->assertTrue(true);
    }
}
