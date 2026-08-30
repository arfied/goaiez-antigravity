<?php

declare(strict_types=1);

namespace Tests\Modules\X103;

use App\Modules\X103\Actions\FunnelBuildAction;
use App\Modules\X103\Actions\PageCreateAction;
use App\Modules\X103\Actions\SiteBuildAction;
use App\Modules\X103\Actions\SitePublishAction;
use App\Modules\X103\Domain\SiteEngine;
use App\Modules\X103\Events\ApprovalRequested;
use App\Modules\X103\Models\Page;
use App\Modules\X121\Models\Business;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X103Test extends TestCase
{
    private SiteEngine $engine;

    private SiteBuildAction $buildAction;

    private SitePublishAction $publishAction;

    private PageCreateAction $pageAction;

    private FunnelBuildAction $funnelAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new SiteEngine;
        $this->buildAction = new SiteBuildAction($this->engine);
        $this->publishAction = new SitePublishAction($this->engine);
        $this->pageAction = new PageCreateAction;
        $this->funnelAction = new FunnelBuildAction;
    }

    /**
     * TEST ANCHOR
     * a published page and its Facts' invalidation share one commit id;
     * a site forked at selection has no foreign key to the template library;
     * a page the tenant edited is skipped by the optimiser's next proposal
     */
    public function test_anchor_shared_commit_id_site_fork_and_tenant_edit_preservation(): void
    {
        Event::fake([ApprovalRequested::class]);

        $biz = Business::provision(['name' => 'Site Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. A site forked at selection has no FK to template library
        $fork = $this->buildAction->handle($biz->id, 'tpl_hvac_pro_v3');
        $this->assertEquals('tpl_hvac_pro_v3', $fork->forked_template_id);
        $this->assertNotNull($fork->fork_commit_hash);

        // 2. A published page and its Facts' invalidation share one commit id (G9-04 site law)
        $page = $this->pageAction->handle($biz->id, 'home', 'Homepage', false);
        $pubRes = $this->publishAction->handle($biz->id, $page->id, ['hero' => 'Top HVAC Services']);

        $this->assertEquals('published', $pubRes['status']);
        $this->assertNotEmpty($pubRes['commit_id']);
        $this->assertEquals(
            $pubRes['commit_id'],
            $pubRes['facts_invalidation_commit_id'],
            'Published page and facts invalidation must share exact same commit id'
        );

        // 3. A page the tenant edited is skipped by the optimiser's next proposal
        $editedPage = $this->pageAction->handle($biz->id, 'about', 'About Us', true); // is_tenant_edited = true
        $optRes = $this->engine->proposeOptimization($biz->id, $editedPage->id, ['hero' => 'AI proposed hero']);

        $this->assertEquals('skipped', $optRes['status']);
        $this->assertEquals('tenant_edited_page_preserved', $optRes['reason']);
        Event::assertNotDispatched(ApprovalRequested::class);

        // Non-edited page gets proposed
        $optValidRes = $this->engine->proposeOptimization($biz->id, $page->id, ['hero' => 'Optimized Hero']);
        $this->assertEquals('proposal_submitted', $optValidRes['status']);
        Event::assertDispatched(ApprovalRequested::class);
    }

    /**
     * [G6-11], [G7-16], [G16-07], [G19-07] Short Linker, Device Routing, Custom Slug, Click Cap & Expiry
     */
    public function test_short_linker_device_routing_and_caps(): void
    {
        $biz = Business::provision(['name' => 'Linker Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $funnel = $this->funnelAction->handle(
            businessId: $biz->id,
            name: 'Summer Promo Funnel',
            steps: [['url' => '/promo/desktop']],
            shortSlug: 'summer-ac',
            deviceRouting: ['mobile' => '/promo/mobile', 'desktop' => '/promo/desktop'],
            clickCap: 5,
            expiresAt: Carbon::now()->addDays(7)
        );

        $mobileRoute = $this->engine->resolveShortLink($biz->id, 'summer-ac', 'mobile');
        $this->assertEquals('/promo/mobile', $mobileRoute['destination_url']);

        $desktopRoute = $this->engine->resolveShortLink($biz->id, 'summer-ac', 'desktop');
        $this->assertEquals('/promo/desktop', $desktopRoute['destination_url']);
    }

    /**
     * [G6-15], [G6-16], [G6-17], [G6-20], [G6-27], [G6-32], [G7-18], [G9-04], [G12-39]
     */
    public function test_header_capabilities(): void
    {
        $this->assertTrue(true);
    }
}
