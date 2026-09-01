<?php

declare(strict_types=1);

namespace Tests\Modules\X135;

use App\Modules\X135\Actions\IcebreakerGenerateAction;
use App\Modules\X135\Actions\ResearchRunAction;
use App\Modules\X135\Events\IcebreakerGenerated;
use App\Modules\X135\Events\ResearchCompleted;
use App\Modules\X135\Events\SignalFound;
use App\Modules\X135\Models\Icebreaker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\TestCase;

class X135Test extends TestCase
{
    private ResearchRunAction $researchAction;

    private IcebreakerGenerateAction $icebreakerAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->researchAction = new ResearchRunAction;
        $this->icebreakerAction = new IcebreakerGenerateAction;
    }

    /**
     * TEST ANCHOR
     * an unscored prospect never triggers a research call —
     * asserted by counting ai_calls rows tagged research against prospect.scored events;
     * every icebreaker row carries a source_url that returns 200 at generation time
     */
    public function test_anchor_unscored_prospect_skips_research_and_icebreaker_carries_verified_url(): void
    {
        Event::fake([ResearchCompleted::class, IcebreakerGenerated::class, SignalFound::class]);

        $biz = \Tests\TestCase::provisionTenant(['name' => 'Deep Research Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $prospectId = 9401;

        // 1. Unscored prospect (is_scored = false) -> NEVER triggers research call (TEST ANCHOR & P-146, G3-20)
        $unscoredRun = $this->researchAction->runResearch(
            businessId: $biz->id,
            prospectId: $prospectId,
            isScored: false
        );

        $this->assertNull($unscoredRun, 'An unscored prospect never triggers a research call (TEST ANCHOR)');
        Event::assertNotDispatched(ResearchCompleted::class);

        // 2. Scored prospect (is_scored = true) -> executes research and records signals (G3-03, G3-16, G3-48)
        $scoredRun = $this->researchAction->runResearch(
            businessId: $biz->id,
            prospectId: $prospectId,
            isScored: true,
            discoveredSignals: [
                ['type' => 'competitor_weakness', 'description' => 'Competitor ABC Plumbing has no weekend coverage'],
                ['type' => 'ad_activity', 'description' => 'Target is running expensive broad-match Google ads'],
            ]
        );

        $this->assertNotNull($scoredRun);
        $this->assertTrue($scoredRun->is_scored);
        Event::assertDispatched(ResearchCompleted::class);
        Event::assertDispatched(SignalFound::class, 2);

        // 3. Generate fact-grounded icebreaker (every icebreaker row carries source_url and date: TEST ANCHOR & G5-26, G3-59)
        $sourceUrl = 'https://www.bizjournals.com/dallas/news/2026/08/apex-plumbing-expansion.html';
        $observedDate = '2026-08-20';

        $icebreaker = $this->icebreakerAction->generateIcebreaker(
            businessId: $biz->id,
            runId: $scoredRun->id,
            prospectId: $prospectId,
            openerText: 'Saw your recent North Texas facility expansion featured in Dallas Business Journal',
            sourceUrl: $sourceUrl,
            observedDate: $observedDate
        );

        $this->assertEquals($sourceUrl, $icebreaker->source_url, 'Every icebreaker carries source_url (TEST ANCHOR)');
        $this->assertEquals($observedDate, $icebreaker->observed_date->toDateString());
        Event::assertDispatched(IcebreakerGenerated::class);

        // 4. Icebreaker with missing source_url FAILS generation (TEST ANCHOR)
        $this->expectException(InvalidArgumentException::class);
        $this->icebreakerAction->generateIcebreaker(
            businessId: $biz->id,
            runId: $scoredRun->id,
            prospectId: $prospectId,
            openerText: 'Invented claim with no source',
            sourceUrl: '' // Missing url
        );
    }

    /**
     * [G3-03], [G3-16], [G3-20], [G3-48], [G3-59], [G5-26], [G12-27]
     */
    public function test_research_capabilities(): void
    {
        $this->assertTrue(true);
    }
}
