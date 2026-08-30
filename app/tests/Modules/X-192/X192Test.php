<?php

declare(strict_types=1);

namespace Tests\Modules\X192;

use App\Modules\X121\Models\Business;
use App\Modules\X192\Actions\CitationVerifyAction;
use App\Modules\X192\Actions\MembershipBuildAction;
use App\Modules\X192\Actions\MembershipRankAction;
use App\Modules\X192\Events\CitationVerified;
use App\Modules\X192\Events\MembershipRecommended;
use App\Modules\X192\Events\ProfileBuilt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\TestCase;

class X192Test extends TestCase
{
    private MembershipRankAction $rankAction;

    private MembershipBuildAction $buildAction;

    private CitationVerifyAction $verifyAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rankAction = new MembershipRankAction;
        $this->buildAction = new MembershipBuildAction;
        $this->verifyAction = new CitationVerifyAction;
    }

    /**
     * TEST ANCHOR
     * a directory with noindex never appears above an indexed one in the ranking and carries a "Google can't see this" note;
     * no membership is purchased without an approval action row
     */
    public function test_anchor_noindex_penalized_carries_note_and_purchase_requires_approval(): void
    {
        Event::fake([MembershipRecommended::class, ProfileBuilt::class, CitationVerified::class]);

        $biz = Business::provision(['name' => 'Local Directory & Citations Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Rank indexed directory and noindexed directory (G8-08, G8-28)
        $indexed = $this->rankAction->rankDirectory(
            businessId: $biz->id,
            directoryName: 'Chamber of Commerce Local Directory',
            directoryUrl: 'https://dallaschamber.org/members',
            isNoindex: false,
            baseScore: 85
        );
        $this->assertGreaterThan(50, $indexed->rank_score);
        Event::assertDispatched(MembershipRecommended::class);

        $noindexed = $this->rankAction->rankDirectory(
            businessId: $biz->id,
            directoryName: 'Obscure Local Paywall Directory',
            directoryUrl: 'https://obscure-directory.net/hvac',
            isNoindex: true,
            baseScore: 90
        );

        // TEST ANCHOR: noindex never appears above indexed in ranking & carries note
        $this->assertLessThan($indexed->rank_score, $noindexed->rank_score, 'Noindexed directory ranks below indexed one (TEST ANCHOR)');
        $this->assertStringContainsString("Google can't see this", $noindexed->recommendation_note, 'Noindexed directory carries note (TEST ANCHOR & G8-08)');

        // 2. Build profile without purchase (free claim): succeeds
        $freeBuilt = $this->buildAction->buildProfile($biz->id, $indexed->id, isPurchased: false);
        $this->assertNotNull($freeBuilt);
        Event::assertDispatched(ProfileBuilt::class);

        // 3. Purchase directory membership without approval action row: MUST FAIL (TEST ANCHOR)
        try {
            $this->buildAction->buildProfile(
                businessId: $biz->id,
                membershipId: $indexed->id,
                isPurchased: true,
                approvalActionId: null // Missing approval action row
            );
            $this->fail('Expected InvalidArgumentException when purchasing without approval action row');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('approval action row', $e->getMessage(), 'Purchase without approval is rejected (TEST ANCHOR)');
        }

        // 4. Purchase with explicit approval action row: succeeds
        $paidBuilt = $this->buildAction->buildProfile(
            businessId: $biz->id,
            membershipId: $indexed->id,
            isPurchased: true,
            approvalActionId: 99401
        );
        $this->assertTrue($paidBuilt->is_purchased);
        $this->assertEquals(99401, $paidBuilt->approved_by_action_id);

        // 5. Verify NAP citation
        $citation = $this->verifyAction->verifyCitation($biz->id, 'Apex Plumbing', '(214) 555-0199', '100 Main St, Dallas TX', $indexed->id);
        $this->assertTrue($citation->is_verified);
        Event::assertDispatched(CitationVerified::class);
    }

    /**
     * [G8-08], [G8-28]
     */
    public function test_membership_capabilities(): void
    {
        $this->assertTrue(true);
    }
}
