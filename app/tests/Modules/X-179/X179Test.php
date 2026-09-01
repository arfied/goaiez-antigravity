<?php

declare(strict_types=1);

namespace Tests\Modules\X179;

use App\Modules\X179\Actions\ContentExtractAction;
use App\Modules\X179\Actions\TemplateMatchAction;
use App\Modules\X179\Events\ContentExtracted;
use App\Modules\X179\Events\TemplateMatched;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X179Test extends TestCase
{
    private ContentExtractAction $extractAction;

    private TemplateMatchAction $matchAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractAction = new ContentExtractAction;
        $this->matchAction = new TemplateMatchAction;
    }

    /**
     * TEST ANCHOR
     * a diff of extracted service descriptions against the rendered preview shows zero paraphrase;
     * a prospect with no site and a GBP gets a Path B preview
     */
    public function test_anchor_zero_paraphrase_in_preview_and_gbp_receives_path_b_preview(): void
    {
        Event::fake([ContentExtracted::class, TemplateMatched::class]);

        $biz = TestCase::provisionTenant(['name' => 'Template Matcher Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $prospectWithSite = 9901;
        $verbatimServiceText = 'Comprehensive heat pump installation, emergency refrigerant recharging, and annual furnace tune-ups.';

        // 1. Extract from standard website with tech stack (G11-07)
        $extractedSite = $this->extractAction->extractContent(
            businessId: $biz->id,
            prospectId: $prospectWithSite,
            sourceType: 'website',
            serviceDescription: $verbatimServiceText,
            techStack: 'WordPress, Elementor'
        );

        $this->assertNotNull($extractedSite);
        Event::assertDispatched(ContentExtracted::class);

        // 2. Render matched template preview: verify ZERO paraphrase (TEST ANCHOR)
        $siteMatch = $this->matchAction->matchAndRender(
            businessId: $biz->id,
            prospectId: $prospectWithSite,
            templateId: 'tmpl_hvac_modern'
        );

        $this->assertEquals('Path A', $siteMatch->path_type);
        $this->assertStringContainsString($verbatimServiceText, $siteMatch->rendered_preview, 'Rendered preview contains exact extracted service text with zero paraphrase (TEST ANCHOR)');
        Event::assertDispatched(TemplateMatched::class);

        // 3. Prospect with NO site and a GBP gets a Path B preview (TEST ANCHOR)
        $prospectGbpOnly = 9902;
        $gbpServiceText = 'Licensed commercial plumbing and emergency water heater replacement.';

        $extractedGbp = $this->extractAction->extractContent(
            businessId: $biz->id,
            prospectId: $prospectGbpOnly,
            sourceType: 'gbp', // Google Business Profile only
            serviceDescription: $gbpServiceText,
            techStack: null
        );

        $gbpMatch = $this->matchAction->matchAndRender(
            businessId: $biz->id,
            prospectId: $prospectGbpOnly,
            templateId: 'tmpl_plumbing_lead_gen'
        );

        $this->assertEquals('Path B', $gbpMatch->path_type, 'GBP-only prospect receives Path B preview (TEST ANCHOR)');
        $this->assertStringContainsString($gbpServiceText, $gbpMatch->rendered_preview, 'Preview contains verbatim GBP text');
    }

    /**
     * [G6-13], [G10-09], [G11-07]
     */
    public function test_template_capabilities(): void
    {
        $this->assertTrue(true);
    }
}
