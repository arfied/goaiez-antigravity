<?php

declare(strict_types=1);

namespace Tests\Modules\X103;

use App\Enums\TenantLinkKind;
use App\Enums\UserRole;
use App\Models\Location;
use App\Models\PlatformSetting;
use App\Models\Review;
use App\Models\TenantLinkRecord;
use App\Models\User;
use App\Modules\CReviews\Models\ReviewRequest;
use App\Modules\CSms\Events\SendRequested;
use App\Modules\X103\Actions\FaqDraftAction;
use App\Modules\X103\Actions\FunnelBuildAction;
use App\Modules\X103\Actions\PageCreateAction;
use App\Modules\X103\Actions\SeoDraftAction;
use App\Modules\X103\Actions\SiteBuildAction;
use App\Modules\X103\Actions\SiteCopyPolishAction;
use App\Modules\X103\Actions\SiteDraftAction;
use App\Modules\X103\Actions\SiteMissingFactsAction;
use App\Modules\X103\Actions\SitePageWeightAction;
use App\Modules\X103\Actions\SitePublishAction;
use App\Modules\X103\Actions\SiteReadabilityAction;
use App\Modules\X103\Domain\SiteBlockRenderer;
use App\Modules\X103\Domain\SiteEngine;
use App\Modules\X103\Events\ApprovalRequested;
use App\Modules\X103\Events\PagePublished;
use App\Modules\X103\Events\SitePublished;
use App\Modules\X103\Models\Funnel;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVersion;
use App\Modules\X103\Models\SiteInventoryImage;
use App\Modules\X103\Models\SiteInventoryPage;
use App\Modules\X103\Ui\Pages;
use App\Modules\X113\Actions\StaffDeactivateAction;
use App\Modules\X113\Actions\StaffInviteAction;
use App\Modules\X113\Models\Role;
use App\Modules\X155\Actions\FormCreateAction;
use App\Modules\X163\Models\PriceBookItem;
use App\Modules\X199\Models\Invoice;
use App\Modules\X199\Models\InvoiceLine;
use App\Services\Facts\BusinessFactKey;
use App\Services\Facts\BusinessFacts;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
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

        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $biz = TestCase::provisionTenant(['name' => 'Site Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. A site forked at selection has no FK to template library
        $fork = $this->buildAction->handle($biz->id, 'tpl_hvac_pro_v3');
        $this->assertEquals('tpl_hvac_pro_v3', $fork->forked_template_id);
        $this->assertNotNull($fork->fork_commit_hash);

        // 2. A published page and its Facts' invalidation share one commit id (the site law's shared-commit half)
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

        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $biz = TestCase::provisionTenant(['name' => 'Site Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = $this->pageAction->handle($biz->id, 'home', 'Homepage', false);
        $pubRes = $this->publishAction->handle($biz->id, $page->id, ['hero' => 'Top HVAC Services']);

        Event::assertDispatched(SitePublished::class, fn ($e) => $e->commitId === $pubRes['commit_id'] && $e->versionId === $pubRes['version_id']);
        Event::assertDispatched(PagePublished::class, fn ($e) => $e->commitId === $pubRes['commit_id'] && $e->versionId === $pubRes['version_id']);
    }

    /**
     * [G6-11], [G7-16] Short Linker, Device Routing, Custom Slug
     */
    public function test_short_linker_device_routing_and_caps(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
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

    /** [G16-07] (R245) an expiring short link (P-072) */
    public function test_g16_07_an_expired_short_link_is_refused(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $biz = TestCase::provisionTenant(['name' => 'Linker Expiry Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $slug1 = 'expired-promo';
        $this->funnelAction->handle(
            businessId: $biz->id,
            name: 'Expired Funnel',
            steps: [['url' => '/promo']],
            shortSlug: $slug1,
            deviceRouting: [],
            clickCap: null,
            expiresAt: Carbon::now()->subDays(1)
        );

        $res1 = $this->engine->resolveShortLink($biz->id, $slug1);
        $this->assertSame('expired', $res1['status']);
        $this->assertArrayNotHasKey('destination_url', $res1);

        $slug2 = 'future-promo';
        $this->funnelAction->handle(
            businessId: $biz->id,
            name: 'Future Funnel',
            steps: [['url' => '/promo']],
            shortSlug: $slug2,
            deviceRouting: [],
            clickCap: null,
            expiresAt: Carbon::now()->addDays(7)
        );

        $res2 = $this->engine->resolveShortLink($biz->id, $slug2);
        $this->assertSame('routed', $res2['status']);
        $this->assertArrayHasKey('destination_url', $res2);
    }

    /** [G19-07] (R245) expiring and click-capped short links */
    public function test_g19_07_a_capped_short_link_is_refused(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $biz = TestCase::provisionTenant(['name' => 'Linker Cap Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $slug = 'capped-promo';
        $this->funnelAction->handle(
            businessId: $biz->id,
            name: 'Capped Funnel',
            steps: [['url' => '/promo']],
            shortSlug: $slug,
            deviceRouting: [],
            clickCap: 3,
            expiresAt: null
        );

        $res1 = $this->engine->resolveShortLink($biz->id, $slug);
        $this->assertSame('routed', $res1['status']);
        $this->assertSame(1, $res1['clicks_count']);

        $res2 = $this->engine->resolveShortLink($biz->id, $slug);
        $this->assertSame('routed', $res2['status']);
        $this->assertSame(2, $res2['clicks_count']);

        $res3 = $this->engine->resolveShortLink($biz->id, $slug);
        $this->assertSame('routed', $res3['status']);
        $this->assertSame(3, $res3['clicks_count']);

        $res4 = $this->engine->resolveShortLink($biz->id, $slug);
        $this->assertSame('capped', $res4['status']);
        $this->assertArrayNotHasKey('destination_url', $res4);

        $funnel = Funnel::where('business_id', $biz->id)->where('short_slug', $slug)->first();
        $this->assertSame(3, $funnel->clicks_count);
    }

    public function test_g9_04_every_built_page_version_carries_the_pixel(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $biz = TestCase::provisionTenant(['name' => 'Pixel Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = $this->pageAction->handle($biz->id, 'pixel', 'Pixel', false);
        $res = $this->publishAction->handle($biz->id, $page->id, []);

        $version = PageVersion::where('business_id', $biz->id)->find($res['version_id']);
        $this->assertTrue($version->pixel_installed);
    }

    /**
     * [G9-04] (R245) the full-stack site law — the pixel is on every site by construction:
     * a version published with no blocks supplied carries all six required types and all
     * four installed flags.
     */
    public function test_g9_04_a_published_version_carries_the_three_site_law_flags(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $biz = TestCase::provisionTenant(['name' => 'Law Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = $this->pageAction->handle($biz->id, 'law', 'Law', false);
        $res = $this->publishAction->handle($biz->id, $page->id, []);

        $version = PageVersion::where('business_id', $biz->id)->find($res['version_id']);
        $this->assertTrue($version->pixel_installed);
        $this->assertTrue($version->chat_installed);
        $this->assertTrue($version->form_capture_installed);
        $this->assertTrue($version->dni_installed);
        $this->assertSame([
            ['type' => 'pixel_script'],
            ['type' => 'chat_widget'],
            ['type' => 'form_capture'],
            ['type' => 'dni_script'],
            ['type' => 'seo_tags'],
            ['type' => 'schema_markup'],
        ], $version->content_blocks);
    }

    /**
     * G12-39
     */
    public function test_g12_39_the_review_widget_is_not_yet_on_the_built_site(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
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
        $types = array_column($version->content_blocks, 'type');
        $this->assertContains('chat', $types);
        $this->assertContains('form_capture', $types);
        $this->assertContains('dni', $types);

        $hasReviewWidget = false;
        foreach ($version->content_blocks as $block) {
            if (isset($block['type']) && in_array($block['type'], ['review_widget', 'review-widget'], true)) {
                $hasReviewWidget = true;
                break;
            }
        }
        $this->assertFalse($hasReviewWidget);
    }

    /** [G6-15] [G6-16] (R245) */
    public function test_g6_16_header_tenant_offer(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $biz = TestCase::provisionTenant(['name' => 'Offer Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = $this->pageAction->handle($biz->id, 'offer', 'Offer', false);

        $blocks = [
            ['type' => 'offer', 'text' => '20% off'],
            ['type' => 'chat'],
        ];
        $res = $this->publishAction->handle($biz->id, $page->id, $blocks);

        $version = PageVersion::where('business_id', $biz->id)->find($res['version_id']);
        $this->assertEquals($blocks, array_slice($version->content_blocks, 0, count($blocks)));
        $this->assertEquals([
            ['type' => 'pixel_script'],
            ['type' => 'chat_widget'],
            ['type' => 'form_capture'],
            ['type' => 'dni_script'],
            ['type' => 'seo_tags'],
            ['type' => 'schema_markup'],
        ], array_slice($version->content_blocks, count($blocks)));
    }

    /** [G6-27] (R245) */
    public function test_g6_27_header_c_sms(): void
    {
        Http::fake();
        Event::fake([SendRequested::class]);

        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $biz = TestCase::provisionTenant(['name' => 'SMS Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = $this->pageAction->handle($biz->id, 'sms-page', 'SMS Page', false);
        $this->publishAction->handle($biz->id, $page->id, []);

        Http::assertNothingSent();
        PlatformSetting::query()->where('key', 'ai.monthly_cap_per_tenant')->delete();
        Event::assertNotDispatched(SendRequested::class);
    }

    /** [G6-32] (R245) */
    public function test_g6_32_header_x199(): void
    {

        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $biz = TestCase::provisionTenant(['name' => 'Invoice Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = $this->pageAction->handle($biz->id, 'invoice-page', 'Invoice Page', false);
        $this->publishAction->handle($biz->id, $page->id, [
            ['type' => 'offer', 'text' => '20% off'],
        ]);

        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, InvoiceLine::count());
    }

    /** [G7-18] (R245) */
    public function test_g7_18_header_c_reviews(): void
    {

        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $biz = TestCase::provisionTenant(['name' => 'Review Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = $this->pageAction->handle($biz->id, 'review-page', 'Review Page', false);
        $res = $this->publishAction->handle($biz->id, $page->id, []);

        $version = PageVersion::where('business_id', $biz->id)->find($res['version_id']);
        $this->assertNotContains('review_widget', array_column($version->content_blocks, 'type'));
        $this->assertSame(0, ReviewRequest::count());
    }

    public function test_page_create_and_site_publish_resolve_from_container(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
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

    public function test_draft_adds_gallery_from_stored_images(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $biz = TestCase::provisionTenant(['name' => 'Gallery Tenant', 'currency' => 'USD']);
        Tenancy::set($biz->id);

        $location = Location::factory()->create(['business_id' => $biz->id, 'website_url' => 'https://example.com', 'website_confirmed_at' => now()]);

        $inventoryPage = SiteInventoryPage::create([
            'business_id' => $biz->id,
            'location_id' => $location->id,
            'url' => 'https://example.com',
        ]);

        // 3 stored images
        SiteInventoryImage::create([
            'business_id' => $biz->id,
            'page_id' => $inventoryPage->id,
            'source_url' => 'https://example.com/a-hero.jpg',
            'path' => 'inventory/hero.jpg',
            'mime' => 'image/jpeg',
            'bytes' => 1234,
            'status' => 'stored',
            'attribution' => 'example.com',
        ]);
        SiteInventoryImage::create([
            'business_id' => $biz->id,
            'page_id' => $inventoryPage->id,
            'source_url' => 'https://example.com/gal1.jpg',
            'path' => 'inventory/gal1.jpg',
            'mime' => 'image/jpeg',
            'bytes' => 1234,
            'status' => 'stored',
            'attribution' => 'example.com',
        ]);
        SiteInventoryImage::create([
            'business_id' => $biz->id,
            'page_id' => $inventoryPage->id,
            'source_url' => 'https://example.com/gal2.jpg',
            'path' => 'inventory/gal2.jpg',
            'mime' => 'image/jpeg',
            'bytes' => 1234,
            'status' => 'stored',
            'attribution' => 'example.com',
        ]);

        $action = app(SiteDraftAction::class);
        $res = $action->handle($biz->id, $location->id);

        $home = Page::where('slug', 'home')->first();
        $this->assertNotNull($home);

        $galleryBlock = collect($home->draft_blocks)->firstWhere('type', 'gallery');
        $this->assertNotNull($galleryBlock);
        $this->assertCount(2, $galleryBlock['items']);
        $this->assertEquals('inventory/gal1.jpg', $galleryBlock['items'][0]['image_path']);
        $this->assertEquals('inventory/gal2.jpg', $galleryBlock['items'][1]['image_path']);
        $this->assertEquals('inventory', $galleryBlock['source']);

        Http::fake();
        $html = (new SiteBlockRenderer)->render($home->draft_blocks, ['tenant_storage_url_prefix' => 'https://site.test/media/']);
        $this->assertEquals(2, substr_count($html, 'https://site.test/media/gal'));
        $this->assertStringContainsString('<img', $html);
        Http::assertNothingSent();
        PlatformSetting::query()->where('key', 'ai.monthly_cap_per_tenant')->delete();
    }

    public function test_draft_adds_team_from_active_staff(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $biz = TestCase::provisionTenant(['name' => 'Team Tenant', 'currency' => 'USD']);
        Tenancy::set($biz->id);

        $location = Location::factory()->create(['business_id' => $biz->id]);

        $role = Role::create(['business_id' => $biz->id, 'name' => 'Tester Role']);

        $inviteAction = app(StaffInviteAction::class);
        $active1 = $inviteAction->handle($biz->id, 't1@example.com', 'UniqueNameAlpha', $role->id);
        $active2 = $inviteAction->handle($biz->id, 't2@example.com', 'UniqueNameBeta', $role->id);
        $deactivated = $inviteAction->handle($biz->id, 't3@example.com', 'UniqueNameGamma', $role->id);

        app(StaffDeactivateAction::class)->handle($biz->id, $deactivated->id);

        $action = app(SiteDraftAction::class);
        $action->handle($biz->id, $location->id);

        $about = Page::where('slug', 'about')->first();
        $this->assertNotNull($about);

        $teamBlock = collect($about->draft_blocks)->firstWhere('type', 'team');
        $this->assertNotNull($teamBlock);
        $this->assertCount(2, $teamBlock['items']);
        $this->assertEquals('Tester Role', $teamBlock['items'][0]['role']);
        $this->assertEquals('staff', $teamBlock['source']);

        $html = (new SiteBlockRenderer)->render($about->draft_blocks, []);

        $this->assertStringContainsString('UniqueNameAlpha', $html);
        $this->assertStringContainsString('UniqueNameBeta', $html);
        $this->assertStringNotContainsString('UniqueNameGamma', $html);
    }

    public function test_draft_skips_team_below_minimum(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $biz = TestCase::provisionTenant(['name' => 'No Team Tenant', 'currency' => 'USD']);
        Tenancy::set($biz->id);
        $location = Location::factory()->create(['business_id' => $biz->id]);

        $action = app(SiteDraftAction::class);
        $action->handle($biz->id, $location->id);

        $about = Page::where('slug', 'about')->first();
        if ($about) {
            $teamBlock = collect($about->draft_blocks)->firstWhere('type', 'team');
            $this->assertNull($teamBlock);
        } else {
            $this->assertNull($about);
        }
    }

    public function test_draft_site_action()
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $biz = TestCase::provisionTenant(['name' => 'Draft Site Tenant', 'currency' => 'USD']);

        $location = Location::where('business_id', $biz->id)->first();
        if (! $location) {
            $location = Location::factory()->create(['business_id' => $biz->id, 'website_url' => 'https://example.com', 'website_confirmed_at' => now()]);
        } else {
            $location->update(['website_url' => 'https://example.com', 'website_confirmed_at' => now()]);
        }

        Tenancy::set($biz->id);

        $invPage = SiteInventoryPage::create([
            'business_id' => $biz->id,
            'location_id' => $location->id,
            'url' => 'https://example.com',
            'title' => 'Home Page',
            'headings' => ['Welcome to Draft Site Tenant H1'],
            'text' => 'This is the first paragraph. '.str_repeat('A', 150),
            'status' => 'fetched',
            'fetched_at' => now(),
            'phones' => ['555-1234'],
            'emails' => ['hello@example.com'],
        ]);

        SiteInventoryPage::create([
            'business_id' => $biz->id,
            'location_id' => $location->id,
            'url' => 'https://example.com/about',
            'title' => 'About Us',
            'text' => 'This is the longest text block. '.str_repeat('B', 1200),
            'status' => 'fetched',
            'fetched_at' => now(),
        ]);

        SiteInventoryImage::create([
            'business_id' => $biz->id,
            'page_id' => $invPage->id,
            'source_url' => 'https://example.com/img1.jpg',
            'path' => 'inventory/img1.jpg',
            'mime' => 'image/jpeg',
            'bytes' => 1234,
            'status' => 'stored',
            'attribution' => 'example.com',
        ]);

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Service 1',
            'price_cents' => 10000,
            'is_confirmed' => true,
            'confirmed_at' => now(),
        ]);

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Service 2',
            'price_cents' => 20000,
            'is_confirmed' => true,
            'confirmed_at' => now(),
        ]);

        Review::factory()->fromGoogle()->approved()->create([
            'location_id' => $location->id,
            'display_on_website' => true,
            'rating' => 5,
            'comment' => 'Great!',
            'reviewer_name' => 'Alice',
        ]);

        Review::factory()->fromGoogle()->approved()->create([
            'location_id' => $location->id,
            'display_on_website' => true,
            'rating' => 2,
            'comment' => 'Bad!',
            'reviewer_name' => 'Bob',
        ]);

        (new TenantLinkRecord)->forceFill([
            'business_id' => $biz->id,
            'kind' => TenantLinkKind::Booking,
            'label' => 'Booking Link',
            'destination' => 'https://booking.com',
        ])->save();

        $action = app(SiteDraftAction::class);
        $res = $action->handle($biz->id, $location->id);

        $this->assertEquals(3, $res['pages']);

        $home = Page::where('slug', 'home')->first();
        $this->assertNotNull($home);

        $types = array_column($home->draft_blocks, 'type');
        $this->assertContains('hero', $types);
        $this->assertContains('about', $types);
        $this->assertContains('services', $types);
        $this->assertContains('reviews_strip', $types);
        $this->assertContains('booking_button', $types);
        $this->assertContains('contact', $types);

        foreach ($home->draft_blocks as $block) {
            $this->assertArrayHasKey('source', $block);
            if ($block['type'] === 'services') {
                $this->assertCount(2, $block['items']);
            }
            if ($block['type'] === 'reviews_strip') {
                $this->assertCount(1, $block['items']); // Only rating 5 is displayable (>= 4)
                $this->assertEquals(5, $block['items'][0]['rating']);
            }
        }

        // no prices -> no services block
        PriceBookItem::where('business_id', $biz->id)->delete();
        Page::where('business_id', $biz->id)->delete();
        $res2 = $action->handle($biz->id, $location->id);

        $home2 = Page::where('slug', 'home')->first();
        $types2 = array_column($home2->draft_blocks, 'type');
        $this->assertNotContains('services', $types2);

        // no booking link -> no button
        TenantLinkRecord::where('business_id', $biz->id)->delete();
        Page::where('business_id', $biz->id)->delete();
        $res3 = $action->handle($biz->id, $location->id);

        $home3 = Page::where('slug', 'home')->first();
        $types3 = array_column($home3->draft_blocks, 'type');
        $this->assertNotContains('booking_button', $types3);

        // slug taken -> skipped; run twice -> second run skips
        $res4 = $action->handle($biz->id, $location->id);
        $this->assertContains('home', $res4['skipped']);
        $this->assertContains('services', $res4['skipped']);
        $this->assertContains('contact', $res4['skipped']);
        $this->assertEquals(0, $res4['pages']);

        // publish through SiteEngine
        Http::fake();

        // HTML contains escaped headline
        $html = (new SiteBlockRenderer)->render($home3->draft_blocks, ['tenant_storage_url_prefix' => 'https://site.test/media/']);
        $this->assertStringContainsString(htmlspecialchars('Welcome to Draft Site Tenant H1', ENT_QUOTES, 'UTF-8'), $html);
        Http::assertNothingSent();
        PlatformSetting::query()->where('key', 'ai.monthly_cap_per_tenant')->delete();
    }

    public function test_polish_rewrites_hero_and_about_and_records_the_model(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $biz = TestCase::provisionTenant(['name' => 'Polish Test', 'currency' => 'USD']);
        Tenancy::set($biz->id);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [
                ['type' => 'hero', 'text' => 'Hero text'],
                ['type' => 'about', 'text' => 'About text'],
                ['type' => 'contact', 'text' => 'Contact text'],
            ],
            'is_published' => false,
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'msg_eval',
                'choices' => [
                    ['message' => ['content' => 'Polished text']],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ]),
        ]);

        $action = app(SiteCopyPolishAction::class);
        $res = $action->handle($biz->id, $page->id);

        $this->assertEquals('polished', $res['status']);
        $this->assertEquals(2, $res['blocks']);
        $this->assertEquals('openai-4o-mini', $res['model']);

        $page->refresh();
        $blocks = $page->draft_blocks;

        $this->assertEquals('Polished text', $blocks[0]['text']);
        $this->assertEquals('Hero text', $blocks[0]['original_text']);
        $this->assertEquals('ai', $blocks[0]['source']);
        $this->assertEquals('openai-4o-mini', $blocks[0]['model']);

        $this->assertEquals('Polished text', $blocks[1]['text']);
        $this->assertEquals('About text', $blocks[1]['original_text']);
        $this->assertEquals('ai', $blocks[1]['source']);
        $this->assertEquals('openai-4o-mini', $blocks[1]['model']);

        $this->assertEquals('Contact text', $blocks[2]['text']);
        $this->assertArrayNotHasKey('original_text', $blocks[2]);

        Http::assertSentCount(2);
    }

    public function test_polish_refuses_when_the_budget_is_out(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $biz = TestCase::provisionTenant(['name' => 'Budget Test', 'currency' => 'USD']);
        Tenancy::set($biz->id);

        PlatformSetting::write('ai.monthly_cap_per_tenant', 0, 'test');

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [
                ['type' => 'hero', 'text' => 'Hero text'],
            ],
            'is_published' => false,
        ]);

        Http::fake();

        $action = app(SiteCopyPolishAction::class);
        $res = $action->handle($biz->id, $page->id);

        $this->assertEquals('refused', $res['status']);
        $this->assertEquals('ai_cost_cap_reached', $res['reason'] ?? $res['status']); // fallback in case it's different

        $page->refresh();
        $blocks = $page->draft_blocks;
        $this->assertEquals('Hero text', $blocks[0]['text']);
        $this->assertArrayNotHasKey('original_text', $blocks[0]);

        Http::assertNothingSent();
        PlatformSetting::query()->where('key', 'ai.monthly_cap_per_tenant')->delete();
    }

    public function test_restore_puts_the_original_back(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $biz = TestCase::provisionTenant(['name' => 'Restore Test', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [
                [
                    'type' => 'hero',
                    'text' => 'Polished hero',
                    'original_text' => 'Original hero',
                    'source' => 'ai',
                    'model' => 'openai-4o-mini',
                ],
            ],
            'is_published' => false,
        ]);

        $component = Livewire::actingAs($owner)
            ->test(Pages::class)
            ->call('restoreOriginal', $page->id);

        $page->refresh();
        $blocks = $page->draft_blocks;

        $this->assertEquals('Original hero', $blocks[0]['text']);
        $this->assertArrayNotHasKey('original_text', $blocks[0]);
        $this->assertArrayNotHasKey('source', $blocks[0]);
        $this->assertArrayNotHasKey('model', $blocks[0]);
    }

    public function test_faq_draft_asks_the_router_once_and_parks_the_answer(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $biz = TestCase::provisionTenant(['name' => 'Faq Test', 'currency' => 'USD']);
        Tenancy::set($biz->id);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'faq',
            'title' => 'FAQ',
            'draft_blocks' => [],
            'is_published' => false,
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'msg_faq',
                'choices' => [
                    ['message' => ['content' => json_encode(['items' => [
                        ['question' => 'Q1', 'answer' => 'A1'],
                        ['question' => 'Q2', 'answer' => 'A2'],
                    ]])]],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ]),
        ]);

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Service 1',
            'price_cents' => 1000,
            'is_confirmed' => true,
            'is_sample' => false,
            'confirmed_at' => now(),
        ]);

        $action = app(FaqDraftAction::class);
        $res = $action->handle($biz->id, $page->id);

        $this->assertEquals('drafted', $res['status']);

        $page->refresh();
        $meta = $page->draft_meta;
        $this->assertNotNull($meta['pending_faq']);
        $this->assertCount(2, $meta['pending_faq']['items']);
        $this->assertEquals('Q1', $meta['pending_faq']['items'][0]['question']);
        $this->assertEmpty($page->draft_blocks);

        Http::assertSentCount(1);
    }

    public function test_faq_draft_refuses_when_the_budget_is_out(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $biz = TestCase::provisionTenant(['name' => 'Faq Budget Test', 'currency' => 'USD']);
        Tenancy::set($biz->id);

        PlatformSetting::write('ai.monthly_cap_per_tenant', 0, 'test');

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'faq',
            'title' => 'FAQ',
            'draft_blocks' => [],
            'is_published' => false,
        ]);

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Service 1',
            'price_cents' => 1000,
            'is_confirmed' => true,
            'is_sample' => false,
            'confirmed_at' => now(),
        ]);

        Http::fake();

        $action = app(FaqDraftAction::class);
        $res = $action->handle($biz->id, $page->id);

        $this->assertEquals('refused', $res['status']);

        $page->refresh();
        $this->assertNull($page->draft_meta['pending_faq'] ?? null);

        Http::assertNothingSent();
        PlatformSetting::query()->where('key', 'ai.monthly_cap_per_tenant')->delete();
    }

    public function test_faq_place_moves_the_pairs_into_one_block_and_render_shows_them(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['name' => 'Faq Place Test', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'faq',
            'title' => 'FAQ',
            'draft_blocks' => [],
            'draft_meta' => [
                'pending_faq' => [
                    'items' => [
                        ['question' => 'Is it fast?', 'answer' => 'Yes.'],
                        ['question' => 'Is it cheap?', 'answer' => 'Very.'],
                    ],
                    'model' => 'test-model',
                    'drafted_at' => now()->toIso8601String(),
                ],
            ],
            'is_published' => false,
        ]);

        $component = Livewire::actingAs($owner)
            ->test(Pages::class)
            ->call('placeFaq', $page->id);

        $page->refresh();
        $this->assertNull($page->draft_meta['pending_faq'] ?? null);
        $blocks = $page->draft_blocks;
        $this->assertCount(1, $blocks);
        $this->assertEquals('faq', $blocks[0]['type']);
        $this->assertEquals('ai', $blocks[0]['source']);
        $this->assertCount(2, $blocks[0]['items']);

        $renderer = app(SiteBlockRenderer::class);
        $html = $renderer->render($blocks, []);
        $this->assertStringContainsString('Is it fast?', $html);
        $this->assertStringContainsString('Is it cheap?', $html);
    }

    public function test_seo_draft_writes_title_and_description_within_limits(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        PlatformSetting::write('sites.seo.title_max_chars', 60, 'test');
        PlatformSetting::write('sites.seo.description_max_chars', 155, 'test');
        $biz = TestCase::provisionTenant(['name' => 'SEO Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [
                ['type' => 'hero', 'text' => 'Hero text'],
            ],
            'is_published' => false,
        ]);

        $longTitle = 'This is a very long title that exceeds the limit of sixty characters by a significant margin';

        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'msg_seo',
                'choices' => [
                    ['message' => ['content' => json_encode(['title' => $longTitle, 'description' => 'Short desc'])]],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
            ]),
        ]);

        $action = app(SeoDraftAction::class);
        $res = $action->handle($biz->id, $page->id);

        $this->assertEquals('drafted', $res['status']);

        $page->refresh();
        $this->assertTrue(mb_strlen($page->seo_title) <= 60);
        $this->assertEquals('Short desc', $page->seo_description);

        Http::assertSentCount(1);
    }

    public function test_seo_draft_refuses_when_the_budget_is_out(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 0, 'test');
        $biz = TestCase::provisionTenant(['name' => 'SEO Tenant No Budget']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [],
            'is_published' => false,
        ]);

        Http::fake();

        $action = app(SeoDraftAction::class);
        $res = $action->handle($biz->id, $page->id);

        $this->assertEquals('refused', $res['status']);
        $this->assertNull($page->refresh()->seo_title);

        Http::assertNothingSent();
        PlatformSetting::query()->where('key', 'ai.monthly_cap_per_tenant')->delete();
    }

    public function test_the_draft_carries_a_form_block_when_the_tenant_has_a_form(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Form Draft Tenant']);
        Tenancy::set((int) $biz->id);
        $location = Location::factory()->create(['business_id' => $biz->id]);

        $formAction = app(FormCreateAction::class);
        $form = $formAction->handle($biz->id, 'Lead form');

        $draftAction = app(SiteDraftAction::class);
        $res = $draftAction->handle($biz->id, $location->id);

        $this->assertContains('forms', $res['sources']);

        $homePage = Page::where('business_id', $biz->id)->where('slug', 'home')->first();
        $this->assertNotNull($homePage);
        $formBlock = collect($homePage->draft_blocks)->firstWhere('type', 'form');

        $this->assertNotNull($formBlock);
        $this->assertCount(4, $formBlock['fields']);
        $this->assertEquals('website_url', $formBlock['honeypot']);
    }

    public function test_the_draft_carries_no_form_block_when_the_tenant_has_none(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'No Form Draft Tenant']);
        $location = Location::factory()->create(['business_id' => $biz->id]);

        $draftAction = app(SiteDraftAction::class);
        $res = $draftAction->handle($biz->id, $location->id);

        $this->assertContains('forms', $res['sources_without_data']);

        $homePage = Page::where('business_id', $biz->id)->where('slug', 'home')->first();
        $this->assertNotNull($homePage);
        $formBlock = collect($homePage->draft_blocks)->firstWhere('type', 'form');
        $this->assertNull($formBlock);
    }

    public function test_the_rendered_form_posts_to_the_live_capture_route(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Render Form Tenant']);

        $block = [
            'type' => 'form',
            'definition_id' => 888,
            'fields' => [
                ['name' => 'phone', 'label' => 'Phone', 'type' => 'tel'],
            ],
            'required' => ['phone'],
            'honeypot' => 'website_url',
        ];

        $deployHash = 'test123hash';
        $baseRoute = route('x-157.site', ['business' => $biz->id, 'deploy_hash' => $deployHash], absolute: true);

        $context = [
            'form_action_base' => $baseRoute,
        ];

        $renderer = app(SiteBlockRenderer::class);
        $html = $renderer->render([$block], $context);

        $expectedAction = rtrim($baseRoute, '/').'/forms/888';
        $this->assertStringContainsString('action="'.$expectedAction.'"', $html);
        $this->assertStringContainsString('name="website_url"', $html);
        $this->assertStringContainsString('name="phone"', $html);
        $this->assertStringContainsString('required', $html);
    }

    public function test_the_drafted_contact_block_carries_the_locations_opening_hours(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $biz = TestCase::provisionTenant(['name' => 'Hours Draft Test', 'currency' => 'USD']);
        Tenancy::set($biz->id);

        $location = Location::factory()->create([
            'business_id' => $biz->id,
            'opening_hours' => [['day' => 'Monday', 'open' => '08:00', 'close' => '17:00']],
        ]);

        $action = app(SiteDraftAction::class);
        $action->handle($biz->id, $location->id);

        $home = Page::where('slug', 'home')->first();
        $contactBlock = collect($home->draft_blocks)->firstWhere('type', 'contact');

        $this->assertNotNull($contactBlock);
        $this->assertArrayHasKey('hours', $contactBlock);
        $this->assertEquals('Monday', $contactBlock['hours'][0]['day']);
        $this->assertStringContainsString('hours: location', $contactBlock['source']);

        Page::query()->delete();
        $location->opening_hours = null;
        $location->save();
        $location->refresh();
        $action->handle($biz->id, $location->id);

        $home = Page::where('slug', 'home')->first();
        $contactBlock = collect($home->draft_blocks)->firstWhere('type', 'contact');
        $this->assertArrayNotHasKey('hours', $contactBlock);
    }

    public function test_a_review_the_owner_ticked_but_moderation_has_not_approved_does_not_reach_the_draft(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Site Review Filter Tenant']);
        Tenancy::set((int) $biz->id);
        $location = Location::factory()->create(['business_id' => $biz->id]);

        Review::factory()->fromGoogle()->approved()->create(['location_id' => $location->id, 'display_on_website' => true, 'rating' => 5, 'comment' => 'Distinctive approved review 4471', 'reviewer_name' => 'Alice']);
        Review::factory()->fromGoogle()->create(['location_id' => $location->id, 'display_on_website' => true, 'rating' => 5, 'comment' => 'Distinctive pending review 4472']);
        Review::factory()->approved()->create(['location_id' => $location->id, 'display_on_website' => true, 'rating' => 5, 'moderation_flags' => ['reviewed' => true], 'flagged_at' => now(), 'comment' => 'Distinctive flagged review 4473']);
        Review::factory()->approved()->create(['location_id' => $location->id, 'display_on_website' => true, 'rating' => 5, 'comment' => 'Distinctive unmoderated first-party review 4474']);

        app(SiteDraftAction::class)->handle($biz->id, $location->id);

        $home = Page::where('slug', 'home')->first();
        $strip = collect($home->draft_blocks)->firstWhere('type', 'reviews_strip');
        $this->assertNotNull($strip);
        $texts = array_column($strip['items'], 'text');
        $this->assertSame(['Distinctive approved review 4471'], $texts);
    }

    public function test_faq_drafting_reads_only_moderated_reviews(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $biz = TestCase::provisionTenant(['name' => 'FAQ Review Filter Tenant']);
        Tenancy::set((int) $biz->id);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'faq',
            'title' => 'FAQ',
            'draft_blocks' => [],
            'is_published' => false,
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'msg_faq',
                'choices' => [
                    ['message' => ['content' => json_encode(['items' => [
                        ['question' => 'Q1', 'answer' => 'A1'],
                    ]])]],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ]),
        ]);

        Review::factory()->fromGoogle()->approved()->create(['business_id' => $biz->id, 'display_on_website' => true, 'rating' => 5, 'comment' => 'Distinctive approved review 4471', 'reviewer_name' => 'Alice']);
        Review::factory()->fromGoogle()->create(['business_id' => $biz->id, 'display_on_website' => true, 'rating' => 5, 'comment' => 'Distinctive pending review 4472']);
        Review::factory()->approved()->create(['business_id' => $biz->id, 'display_on_website' => true, 'rating' => 5, 'moderation_flags' => ['reviewed' => true], 'flagged_at' => now(), 'comment' => 'Distinctive flagged review 4473']);
        Review::factory()->approved()->create(['business_id' => $biz->id, 'display_on_website' => true, 'rating' => 5, 'comment' => 'Distinctive unmoderated first-party review 4474']);

        $action = app(FaqDraftAction::class);
        $action->handle($biz->id, $page->id);

        Http::assertSent(fn ($r) => str_contains($r->body(), 'Distinctive approved review 4471') && ! str_contains($r->body(), 'Distinctive pending review 4472'));
    }

    public function test_missing_facts_follow_the_drafts_own_filters(): void
    {
        $biz = $this->provisionTenant();
        $location = Location::where('business_id', $biz->id)->first();
        Tenancy::set($biz->id);

        $action = app(SiteMissingFactsAction::class);

        $review = Review::factory()->fromGoogle()->create([
            'business_id' => $biz->id,
            'location_id' => $location->id,
            'display_on_website' => true,
            'rating' => 5,
        ]);
        $rows = $action->handle($biz->id, $location);
        $this->assertContains('reviews', array_column($rows, 'key'));

        $review->update(['status' => 'approved']);
        $rows = $action->handle($biz->id, $location);
        $this->assertNotContains('reviews', array_column($rows, 'key'));

        $item = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Service 1',
            'price_cents' => 1000,
            'is_confirmed' => false,
            'is_sample' => false,
        ]);
        $rows = $action->handle($biz->id, $location);
        $this->assertContains('services', array_column($rows, 'key'));

        $item->update([
            'is_confirmed' => true,
            'confirmed_at' => now(),
        ]);
        $rows = $action->handle($biz->id, $location);
        $this->assertNotContains('services', array_column($rows, 'key'));
    }

    public function test_the_readability_check_reads_the_draft_and_clears_when_the_fact_is_fixed(): void
    {
        $biz = $this->provisionTenant();
        Tenancy::set($biz->id);

        $home = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'H1', 'image_path' => 'inventory/a.jpg', 'image_alt' => '   '],
                ['type' => 'gallery', 'items' => [['image_path' => 'inventory/b.jpg', 'alt' => 'Distinctive alt 4481'], ['image_path' => 'inventory/c.jpg', 'alt' => '']]],
                ['type' => 'form', 'fields' => [['name' => 'email', 'label' => 'Email', 'type' => 'email'], ['name' => 'phone', 'label' => '', 'type' => 'text']], 'honeypot' => 'website_url'],
                ['type' => 'about', 'text' => 'Conceptualization compartmentalization interoperability standardization characterization implementation representation documentation initialization authentication configuration synchronization administration optimization authentication specification interpretation diversification differentiation classification qualification justification multiplication identification communication experimentation.'],
            ],
        ]);

        $about = Page::create([
            'business_id' => $biz->id,
            'slug' => 'about',
            'title' => 'About',
            'draft_blocks' => [
                ['type' => 'team', 'items' => [['name' => 'A', 'role' => 'B']]],
            ],
        ]);

        $action = app(SiteReadabilityAction::class);
        $rows = $action->handle($biz->id);

        $homeKeys = array_values(array_column(array_filter($rows, fn ($r) => $r['page'] === 'home'), 'key'));
        $this->assertContains('picture_description', $homeKeys);
        $this->assertContains('form_label', $homeKeys);
        $this->assertContains('reading_grade', $homeKeys);
        $this->assertNotContains('no_heading', $homeKeys);

        $homePicRow = array_values(array_filter($rows, fn ($r) => $r['page'] === 'home' && $r['key'] === 'picture_description'))[0] ?? null;
        $this->assertSame('2 pictures have no description', $homePicRow['label']);

        $homeFormRow = array_values(array_filter($rows, fn ($r) => $r['page'] === 'home' && $r['key'] === 'form_label'))[0] ?? null;
        $this->assertSame('1 form field has no label', $homeFormRow['label']);

        $aboutKeys = array_values(array_column(array_filter($rows, fn ($r) => $r['page'] === 'about'), 'key'));
        $this->assertContains('no_heading', $aboutKeys);
        $this->assertNotContains('picture_description', $aboutKeys);
        $this->assertNotContains('form_label', $aboutKeys);
        $this->assertNotContains('reading_grade', $aboutKeys);

        $home->update([
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'H1', 'image_path' => 'inventory/a.jpg', 'image_alt' => 'Distinctive alt 4482'],
                ['type' => 'gallery', 'items' => [['image_path' => 'inventory/b.jpg', 'alt' => 'Distinctive alt 4481'], ['image_path' => 'inventory/c.jpg', 'alt' => 'Distinctive alt 4482']]],
                ['type' => 'form', 'fields' => [['name' => 'email', 'label' => 'Email', 'type' => 'email'], ['name' => 'phone', 'label' => 'Phone', 'type' => 'text']], 'honeypot' => 'website_url'],
                ['type' => 'about', 'text' => 'It is good.'],
            ],
        ]);

        $rows2 = $action->handle($biz->id);
        $homeKeys2 = array_values(array_column(array_filter($rows2, fn ($r) => $r['page'] === 'home'), 'key'));
        $this->assertEmpty($homeKeys2);
    }

    public function test_the_draft_reads_the_owners_facts_sheet(): void
    {
        $biz = self::provisionTenant();
        $loc = $biz->locations->first();
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz->update(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);
        Tenancy::set($biz->id);

        $facts = app(BusinessFacts::class);
        $facts->set($biz->id, BusinessFactKey::TAGLINE, 'Distinctive tagline 4471');
        $facts->set($biz->id, BusinessFactKey::DESCRIPTION, 'Distinctive description 4472');
        $facts->set($biz->id, BusinessFactKey::LICENCE_NUMBER, 'LIC-4473');

        $action = app(SiteDraftAction::class);
        $action->handle($biz->id, $loc->id);

        $home = Page::where('business_id', $biz->id)->where('slug', 'home')->first();
        $blocks = $home->draft_blocks;

        $hero = collect($blocks)->firstWhere('type', 'hero');
        $this->assertSame('Distinctive tagline 4471', $hero['subline']);
        $this->assertStringContainsString('tagline: facts', $hero['source']);

        $about = collect($blocks)->firstWhere('type', 'about');
        $this->assertSame('Distinctive description 4472', $about['text']);
        $this->assertSame('facts', $about['source']);

        $contact = Page::where('business_id', $biz->id)->where('slug', 'contact')->first();
        $contactBlock = collect($contact->draft_blocks)->firstWhere('type', 'contact');
        $this->assertSame('LIC-4473', $contactBlock['facts']['licence_number']);
        $this->assertStringContainsString('facts: owner', $contactBlock['source']);

        // Second tenant with no facts
        $biz2 = self::provisionTenant();
        $loc2 = $biz2->locations->first();
        Tenancy::set($biz2->id);

        $action->handle($biz2->id, $loc2->id);

        $home2 = Page::where('business_id', $biz2->id)->where('slug', 'home')->first();
        $hero2 = collect($home2->draft_blocks)->firstWhere('type', 'hero');
        $this->assertSame('', $hero2['subline']);

        $about2 = collect($home2->draft_blocks)->firstWhere('type', 'about');
        $this->assertNull($about2);
    }

    public function test_the_draft_carries_each_pictures_description_and_never_invents_one(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $biz = TestCase::provisionTenant(['name' => 'Gallery Tenant', 'currency' => 'USD']);
        Tenancy::set($biz->id);

        $location = Location::factory()->create(['business_id' => $biz->id, 'website_url' => 'https://example.com', 'website_confirmed_at' => now()]);

        $inventoryPage = SiteInventoryPage::create([
            'business_id' => $biz->id,
            'location_id' => $location->id,
            'url' => 'https://example.com',
        ]);

        SiteInventoryImage::create([
            'business_id' => $biz->id,
            'page_id' => $inventoryPage->id,
            'source_url' => 'https://example.com/a-hero.jpg',
            'path' => 'inventory/hero.jpg',
            'mime' => 'image/jpeg',
            'bytes' => 1234,
            'status' => 'stored',
            'attribution' => 'example.com',
            'alt' => 'Distinctive alt 4474',
        ]);
        SiteInventoryImage::create([
            'business_id' => $biz->id,
            'page_id' => $inventoryPage->id,
            'source_url' => 'https://example.com/gal1.jpg',
            'path' => 'inventory/gal1.jpg',
            'mime' => 'image/jpeg',
            'bytes' => 1234,
            'status' => 'stored',
            'attribution' => 'example.com',
        ]);
        SiteInventoryImage::create([
            'business_id' => $biz->id,
            'page_id' => $inventoryPage->id,
            'source_url' => 'https://example.com/gal2.jpg',
            'path' => 'inventory/gal2.jpg',
            'mime' => 'image/jpeg',
            'bytes' => 1234,
            'status' => 'stored',
            'attribution' => 'example.com',
            'alt' => 'Distinctive alt 4475',
        ]);

        $action = app(SiteDraftAction::class);
        $res = $action->handle($biz->id, $location->id);

        $home = Page::where('slug', 'home')->first();
        $this->assertNotNull($home);

        $heroBlock = collect($home->draft_blocks)->firstWhere('type', 'hero');
        $this->assertSame('Distinctive alt 4474', $heroBlock['image_alt']);

        $galleryBlock = collect($home->draft_blocks)->firstWhere('type', 'gallery');
        $this->assertNotNull($galleryBlock);
        $this->assertCount(2, $galleryBlock['items']);
        $this->assertSame('', $galleryBlock['items'][0]['alt']);
        $this->assertSame('Distinctive alt 4475', $galleryBlock['items'][1]['alt']);
        PlatformSetting::query()->where('key', 'ai.monthly_cap_per_tenant')->delete();
    }

    public function test_the_draft_carries_each_pictures_size_when_the_copy_recorded_one(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $biz = TestCase::provisionTenant(['name' => 'Gallery Tenant', 'currency' => 'USD']);
        Tenancy::set($biz->id);

        $location = Location::factory()->create(['business_id' => $biz->id, 'website_url' => 'https://example.com', 'website_confirmed_at' => now()]);

        $inventoryPage = SiteInventoryPage::create([
            'business_id' => $biz->id,
            'location_id' => $location->id,
            'url' => 'https://example.com',
        ]);

        SiteInventoryImage::create([
            'business_id' => $biz->id,
            'page_id' => $inventoryPage->id,
            'source_url' => 'https://example.com/a-hero.jpg',
            'path' => 'inventory/hero.jpg',
            'mime' => 'image/jpeg',
            'bytes' => 1234,
            'status' => 'stored',
            'attribution' => 'example.com',
            'width' => 1280,
            'height' => 720,
        ]);
        SiteInventoryImage::create([
            'business_id' => $biz->id,
            'page_id' => $inventoryPage->id,
            'source_url' => 'https://example.com/gal1.jpg',
            'path' => 'inventory/gal1.jpg',
            'mime' => 'image/jpeg',
            'bytes' => 1234,
            'status' => 'stored',
            'attribution' => 'example.com',
        ]);
        SiteInventoryImage::create([
            'business_id' => $biz->id,
            'page_id' => $inventoryPage->id,
            'source_url' => 'https://example.com/gal2.jpg',
            'path' => 'inventory/gal2.jpg',
            'mime' => 'image/jpeg',
            'bytes' => 1234,
            'status' => 'stored',
            'attribution' => 'example.com',
            'width' => 800,
            'height' => 600,
        ]);

        $action = app(SiteDraftAction::class);
        $res = $action->handle($biz->id, $location->id);

        $home = Page::where('slug', 'home')->first();
        $this->assertNotNull($home);

        $heroBlock = collect($home->draft_blocks)->firstWhere('type', 'hero');
        $this->assertSame(1280, $heroBlock['image_width']);
        $this->assertSame(720, $heroBlock['image_height']);

        $galleryBlock = collect($home->draft_blocks)->firstWhere('type', 'gallery');
        $this->assertNotNull($galleryBlock);
        $this->assertCount(2, $galleryBlock['items']);
        $this->assertArrayNotHasKey('width', $galleryBlock['items'][0]);
        $this->assertSame(800, $galleryBlock['items'][1]['width']);
        PlatformSetting::query()->where('key', 'ai.monthly_cap_per_tenant')->delete();
    }

    public function test_the_page_weight_reads_the_stored_pictures_and_the_rendered_text(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Weight Tenant', 'currency' => 'USD']);
        Tenancy::set($biz->id);

        $location = Location::factory()->create(['business_id' => $biz->id]);
        $inventoryPage = SiteInventoryPage::create([
            'business_id' => $biz->id,
            'location_id' => $location->id,
            'url' => 'https://example.com',
        ]);

        SiteInventoryImage::create([
            'business_id' => $biz->id,
            'page_id' => $inventoryPage->id,
            'source_url' => 'https://example.com/w1.jpg',
            'path' => 'inventory/w1.jpg',
            'mime' => 'image/jpeg',
            'bytes' => 11264,
            'status' => 'stored',
            'attribution' => 'example.com',
        ]);
        SiteInventoryImage::create([
            'business_id' => $biz->id,
            'page_id' => $inventoryPage->id,
            'source_url' => 'https://example.com/w2.jpg',
            'path' => 'inventory/w2.jpg',
            'mime' => 'image/jpeg',
            'bytes' => 22528,
            'status' => 'stored',
            'attribution' => 'example.com',
        ]);
        SiteInventoryImage::create([
            'business_id' => $biz->id,
            'page_id' => $inventoryPage->id,
            'source_url' => 'https://example.com/w3.jpg',
            'path' => 'inventory/w3.jpg',
            'mime' => 'image/jpeg',
            'bytes' => 44032,
            'status' => 'stored',
            'attribution' => 'example.com',
        ]);

        Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Headline', 'image_path' => 'inventory/w1.jpg'],
                ['type' => 'gallery', 'items' => [['image_path' => 'inventory/w2.jpg'], ['image_path' => 'inventory/w3.jpg']]],
                ['type' => 'pixel_script'],
            ],
            'is_published' => false,
        ]);
        Page::create([
            'business_id' => $biz->id,
            'slug' => 'plain',
            'title' => 'Plain',
            'draft_blocks' => [
                ['type' => 'about', 'text' => 'About text'],
            ],
            'is_published' => false,
        ]);
        Page::create([
            'business_id' => $biz->id,
            'slug' => 'missing-img',
            'title' => 'Missing Img',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Headline', 'image_path' => 'inventory/missing.jpg'],
            ],
            'is_published' => false,
        ]);

        $action = app(SitePageWeightAction::class);
        $res = $action->handle($biz->id);

        $this->assertCount(3, $res);

        $this->assertSame('home', $res[0]['page']);
        $this->assertSame(3, $res[0]['images']);
        $this->assertSame(77824, $res[0]['image_bytes']);
        $this->assertSame('w3.jpg', $res[0]['largest']);
        $this->assertSame(44032, $res[0]['largest_bytes']);
        $this->assertGreaterThan(0, $res[0]['html_bytes']);
        $this->assertSame(1, $res[0]['scripts']);

        $this->assertSame('plain', $res[1]['page']);
        $this->assertSame(0, $res[1]['images']);
        $this->assertSame(0, $res[1]['image_bytes']);
        $this->assertNull($res[1]['largest']);
        $this->assertSame(0, $res[1]['largest_bytes']);
        $this->assertGreaterThan(0, $res[1]['html_bytes']);
        $this->assertSame(0, $res[1]['scripts']);

        $this->assertSame('missing-img', $res[2]['page']);
        $this->assertSame(1, $res[2]['images']);
        $this->assertSame(0, $res[2]['image_bytes']);
        $this->assertNull($res[2]['largest']);
        $this->assertSame(0, $res[2]['largest_bytes']);
        $this->assertGreaterThan(0, $res[2]['html_bytes']);
        $this->assertSame(0, $res[2]['scripts']);
    }
}
