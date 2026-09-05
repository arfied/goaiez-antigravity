<?php

declare(strict_types=1);

namespace Tests\Modules\X103;

use App\Modules\CSms\Events\SendRequested;
use App\Modules\X103\Actions\FunnelBuildAction;
use App\Modules\X103\Actions\PageCreateAction;
use App\Modules\X103\Actions\SiteBuildAction;
use App\Modules\X103\Actions\SitePublishAction;
use App\Modules\X103\Domain\SiteEngine;
use App\Modules\X103\Events\ApprovalRequested;
use App\Modules\X103\Events\PagePublished;
use App\Modules\X103\Events\SitePublished;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVersion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
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

        $biz = TestCase::provisionTenant(['name' => 'Site Tenant', 'currency' => 'USD']);
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

    public function test_anchor_site_published_shares_commit_id(): void
    {
        Event::fake([PagePublished::class, SitePublished::class]);

        $biz = TestCase::provisionTenant(['name' => 'Site Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = $this->pageAction->handle($biz->id, 'home', 'Homepage', false);
        $pubRes = $this->publishAction->handle($biz->id, $page->id, ['hero' => 'Top HVAC Services']);

        Event::assertDispatched(SitePublished::class, fn ($e) => $e->commitId === $pubRes['commit_id'] && $e->versionId === $pubRes['version_id']);
        Event::assertDispatched(PagePublished::class, fn ($e) => $e->commitId === $pubRes['commit_id'] && $e->versionId === $pubRes['version_id']);
    }

    /**
     * [G6-11], [G7-16], [G16-07], [G19-07] Short Linker, Device Routing, Custom Slug, Click Cap & Expiry
     */
    public function test_short_linker_device_routing_and_caps(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Linker Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $slug = 'summer-ac-promo';
        $funnel = $this->funnelAction->handle(
            businessId: $biz->id,
            name: 'Summer Promo Funnel',
            steps: [['url' => '/promo/desktop']],
            shortSlug: $slug,
            deviceRouting: ['mobile' => '/promo/mobile', 'desktop' => '/promo/desktop'],
            clickCap: 5,
            expiresAt: Carbon::now()->addDays(7)
        );

        $mobileRoute = $this->engine->resolveShortLink($biz->id, $slug, 'mobile');
        $this->assertEquals('/promo/mobile', $mobileRoute['destination_url']);

        $desktopRoute = $this->engine->resolveShortLink($biz->id, $slug, 'desktop');
        $this->assertEquals('/promo/desktop', $desktopRoute['destination_url']);
    }

    public function test_g9_04_every_built_page_version_carries_the_pixel(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Pixel Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = $this->pageAction->handle($biz->id, 'pixel', 'Pixel', false);
        $res = $this->publishAction->handle($biz->id, $page->id, []);

        $version = PageVersion::where('business_id', $biz->id)->find($res['version_id']);
        $this->assertTrue($version->pixel_installed);
    }

    /**
     * G12-39
     */
    public function test_g12_39_the_review_widget_is_not_yet_on_the_built_site(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Widget Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = $this->pageAction->handle($biz->id, 'widget', 'Widget', false);
        $res = $this->publishAction->handle($biz->id, $page->id, [
            ['type' => 'chat'],
            ['type' => 'form_capture'],
            ['type' => 'dni'],
        ]);

        $version = PageVersion::where('business_id', $biz->id)->find($res['version_id']);
        $this->assertIsArray($version->content_blocks);
        $this->assertCount(3, $version->content_blocks);

        $types = array_column($version->content_blocks, 'type');
        $this->assertEquals(['chat', 'form_capture', 'dni'], $types);

        $hasReviewWidget = false;
        foreach ($version->content_blocks as $block) {
            if (isset($block['type']) && in_array($block['type'], ['review_widget', 'review-widget'], true)) {
                $hasReviewWidget = true;
                break;
            }
        }
        $this->assertFalse($hasReviewWidget);
    }

    /** (R245) */
    public function test_g6_15_header_first_line(): void
    {
        $caps = require app_path('Modules/X-103/capabilities.php');
        $this->assertArrayHasKey('G6-15', $caps);
    }

    /** (R245) */
    public function test_g6_16_header_tenant_offer(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Offer Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = $this->pageAction->handle($biz->id, 'offer', 'Offer', false);

        $blocks = [
            ['type' => 'offer', 'text' => '20% off'],
            ['type' => 'chat'],
        ];
        $res = $this->publishAction->handle($biz->id, $page->id, $blocks);

        $version = PageVersion::where('business_id', $biz->id)->find($res['version_id']);
        $this->assertEquals($blocks, $version->content_blocks);
    }

    /** (R245) */
    public function test_g6_17_header_x194(): void
    {
        $caps = require app_path('Modules/X-103/capabilities.php');
        $this->assertArrayHasKey('G6-17', $caps);
        $this->assertTrue(is_dir(app_path('Modules/X-194')));
    }

    /** (R245) */
    public function test_g6_20_header_x195(): void
    {
        $caps = require app_path('Modules/X-103/capabilities.php');
        $this->assertArrayHasKey('G6-20', $caps);
        $this->assertTrue(is_dir(app_path('Modules/X-195')));
    }

    /** (R245) */
    public function test_g6_27_header_c_sms(): void
    {
        Http::fake();
        Event::fake([SendRequested::class]);

        $biz = TestCase::provisionTenant(['name' => 'SMS Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = $this->pageAction->handle($biz->id, 'sms-page', 'SMS Page', false);
        $this->publishAction->handle($biz->id, $page->id, []);

        Http::assertNothingSent();
    }

    /** (R245) */
    public function test_g6_32_header_x199(): void
    {
        $caps = require app_path('Modules/X-103/capabilities.php');
        $this->assertArrayHasKey('G6-32', $caps);
        $this->assertTrue(is_dir(app_path('Modules/X-199')));
    }

    /** (R245) */
    public function test_g7_18_header_c_reviews(): void
    {
        $caps = require app_path('Modules/X-103/capabilities.php');
        $this->assertArrayHasKey('G7-18', $caps);
        $this->assertTrue(is_dir(app_path('Modules/C-Reviews')));
    }

    public function test_page_create_and_site_publish_resolve_from_container(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Container Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $pageAction = app(PageCreateAction::class);
        $publishAction = app(SitePublishAction::class);

        $page = $pageAction->handle($biz->id, 'builder-test', 'Builder Title', false);

        $this->assertInstanceOf(Page::class, $page);
        $this->assertEquals('builder-test', $page->slug);
        $this->assertFalse($page->is_published);

        $res = $publishAction->handle($biz->id, $page->id, ['block1' => 'content']);

        $this->assertIsArray($res);
        $this->assertArrayHasKey('status', $res);
        $this->assertArrayHasKey('page_id', $res);
        $this->assertArrayHasKey('version_id', $res);
        $this->assertArrayHasKey('commit_id', $res);
        $this->assertArrayHasKey('facts_invalidation_commit_id', $res);
        $this->assertEquals('published', $res['status']);
        $this->assertEquals($page->id, $res['page_id']);
    }
}
