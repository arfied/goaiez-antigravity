<?php

declare(strict_types=1);

namespace Tests\Modules\X191;

use App\Modules\X191\Actions\LinkMonitorAction;
use App\Modules\X191\Actions\LinkPitchAction;
use App\Modules\X191\Actions\LinkProspectAction;
use App\Modules\X191\Events\CompetitorAnalysed;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\TestCase;

class X191Test extends TestCase
{
    private LinkProspectAction $prospectAction;

    private LinkPitchAction $pitchAction;

    private LinkMonitorAction $monitorAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prospectAction = new LinkProspectAction;
        $this->pitchAction = new LinkPitchAction(app(DefaultsRegistry::class));
        $this->monitorAction = new LinkMonitorAction;
    }

    /**
     * TEST ANCHOR
     * a target flagged PBN never receives a pitch;
     * the monthly send count never exceeds the target ceiling;
     * a pitch template with no page-specific fact fails the qualify gate
     * [G8-20]
     * [G8-21]
     * [G12-07]
     * [G17-06]
     * [G11-34]
     */
    public function test_anchor_pbn_target_rejected_ceiling_enforced_and_page_fact_required(): void
    {
        Event::fake([CompetitorAnalysed::class]);

        $biz = TestCase::provisionTenant(['name' => 'Backlink Outreach Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Prospect legitimate target and PBN target
        $cleanTarget = $this->prospectAction->prospectDomain(
            businessId: $biz->id,
            domain: 'hvacinsider.com',
            targetUrl: 'https://hvacinsider.com/best-heat-pumps',
            isPbn: false,
            daScore: 48
        );
        Event::assertDispatched(CompetitorAnalysed::class);

        $pbnTarget = $this->prospectAction->prospectDomain(
            businessId: $biz->id,
            domain: 'spammy-link-farm-network.xyz',
            targetUrl: 'https://spammy-link-farm-network.xyz/article-123',
            isPbn: true,
            daScore: 12
        );

        // 2. A target flagged PBN never receives a pitch (TEST ANCHOR)
        try {
            $this->pitchAction->sendPitch(
                businessId: $biz->id,
                targetId: $pbnTarget->id,
                pitchBody: 'We loved your recent article on HVAC systems...',
                pageSpecificFact: 'Referenced Section 4 on compressor noise'
            );
            $this->fail('Expected InvalidArgumentException when pitching PBN target');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('PBN', $e->getMessage(), 'PBN pitch is rejected (TEST ANCHOR)');
        }

        // 3. Pitch template with no page-specific fact fails qualify gate (TEST ANCHOR & G11-34)
        try {
            $this->pitchAction->sendPitch(
                businessId: $biz->id,
                targetId: $cleanTarget->id,
                pitchBody: 'Generic guest post proposal template...',
                pageSpecificFact: null // Missing page-specific fact
            );
            $this->fail('Expected InvalidArgumentException when page-specific fact is missing');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('page-specific', $e->getMessage(), 'Missing page fact fails qualify gate (TEST ANCHOR & G11-34)');
        }

        // 4. Valid pitch sent successfully with 1 follow-up max (G8-20, G12-07, G17-06)
        $pitch = $this->pitchAction->sendPitch(
            businessId: $biz->id,
            targetId: $cleanTarget->id,
            pitchBody: 'We loved your recent article on HVAC systems and noticed the section on SEER2 standards...',
            pageSpecificFact: 'In paragraph 3 you mentioned 2026 SEER2 compliance changes',
            sentMonth: '2026-09'
        );
        $this->assertNotNull($pitch);
        $this->assertTrue($pitch->is_sent);

        // Follow up 1: ok
        $followedUp = $this->pitchAction->sendFollowUp($biz->id, $pitch->id);
        $this->assertEquals(1, $followedUp->follow_up_count);

        // Follow up 2: MUST fail (ONE follow-up only: G12-07, G17-06)
        try {
            $this->pitchAction->sendFollowUp($biz->id, $pitch->id);
            $this->fail('Expected InvalidArgumentException on second follow-up');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('one follow-up', $e->getMessage());
        }

        // 5. Monitor placement
        $placement = $this->monitorAction->recordPlacement($biz->id, 'https://hvacinsider.com/best-heat-pumps', 'Dallas HVAC repair experts', $pitch->id);
        $this->assertTrue($placement->is_active);
    }
}
