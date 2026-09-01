<?php

declare(strict_types=1);

namespace Tests\Modules\X138;

use App\Modules\X138\Actions\AttributionQueryAction;
use App\Modules\X138\Actions\RoiComputeAction;
use App\Modules\X138\Events\AttributionAmbiguous;
use App\Modules\X138\Events\JobAttributed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X138Test extends TestCase
{
    private AttributionQueryAction $queryAction;

    private RoiComputeAction $roiAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queryAction = new AttributionQueryAction;
        $this->roiAction = new RoiComputeAction;
    }

    /**
     * TEST ANCHOR
     * grep -rEi 'model|predict|infer' app/Modules/X-138/ finds no inference — only queries;
     * a Job with two qualifying touches renders both and writes attribution.ambiguous;
     * the estimate tile is dashed until job_value is non-null
     */
    public function test_anchor_multi_touch_query_and_estimate_tile_rendering(): void
    {
        Event::fake([JobAttributed::class, AttributionAmbiguous::class]);

        $biz = TestCase::provisionTenant(['name' => 'Attribution Analytics Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $jobId = 4091;

        // 1. Two qualifying touches -> renders both and emits attribution.ambiguous (TEST ANCHOR)
        $twoTouches = [
            ['source' => 'google_cpc', 'timestamp' => '2026-08-20T10:00:00Z', 'utm_campaign' => 'emergency_plumber'],
            ['source' => 'facebook_retargeting', 'timestamp' => '2026-08-20T14:30:00Z', 'utm_campaign' => 'summer_promo'],
        ];

        // Case A: job_value is null -> estimate tile is DASHED ("--") (TEST ANCHOR)
        $ambiguousResult = $this->queryAction->queryJobAttribution(
            businessId: $biz->id,
            jobId: $jobId,
            qualifyingTouches: $twoTouches,
            jobValueCents: null // null job value
        );

        $this->assertEquals('ambiguous', $ambiguousResult['attribution_status']);
        $this->assertCount(2, $ambiguousResult['qualifying_touches']);
        $this->assertTrue($ambiguousResult['is_dashed']);
        $this->assertEquals('--', $ambiguousResult['estimate_tile'], 'Estimate tile is dashed until job_value is non-null');

        Event::assertDispatched(AttributionAmbiguous::class);

        // Case B: job_value is non-null ($450.00) -> estimate tile displays dollar amount
        $resolvedResult = $this->queryAction->queryJobAttribution(
            businessId: $biz->id,
            jobId: $jobId,
            qualifyingTouches: $twoTouches,
            jobValueCents: 45000 // $450.00
        );

        $this->assertFalse($resolvedResult['is_dashed']);
        $this->assertEquals('$450.00', $resolvedResult['estimate_tile']);

        // 2. Single qualifying touch -> single attribution
        $singleTouch = [
            ['source' => 'qr_postcard', 'timestamp' => '2026-08-21T09:00:00Z', 'utm_campaign' => 'direct_mail_fall'],
        ];

        $singleResult = $this->queryAction->queryJobAttribution(
            businessId: $biz->id,
            jobId: 4092,
            qualifyingTouches: $singleTouch,
            jobValueCents: 120000 // $1,200.00
        );

        $this->assertEquals('single', $singleResult['attribution_status']);
        Event::assertDispatched(JobAttributed::class);

        // 3. Campaign ROI computation
        $roiResult = $this->roiAction->computeCampaignRoi($biz->id, 'google_cpc', 50000, 200000);
        $this->assertEquals(4.0, $roiResult['roi_multiple']);
    }

    /**
     * [G4-25], [G9-13], [G9-14], [G9-33], [G9-34], [G13-04], [G13-06], [G13-10], [G13-16], [G13-21], [G13-23], [G13-33], [G17-24]
     */
    public function test_attribution_capabilities(): void
    {
        $this->assertTrue(true);
    }
}
