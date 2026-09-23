

declare(strict_types=1);

namespace Tests\Modules\X103;

use App\Modules\CReviews\Models\ReviewRequest;
use App\Modules\CSms\Events\SendRequested;
use App\Modules\X103\Actions\FunnelBuildAction;
use App\Modules\X103\Actions\PageCreateAction;
use App\Modules\X103\Actions\SiteBuildAction;
use App\Modules\X103\Actions\SitePublishAction;
use App\Modules\X103\Domain\SiteEngine;
use App\Modules\X103\Events\ApprovalRequested;
use App\Modules\X103\Events\PagePublished;
use App\Modules\X103\Events\SitePublished;
use App\Modules\X103\Models\Funnel;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVersion;
use App\Modules\X199\Models\Invoice;
use App\Modules\X199\Models\InvoiceLine;
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

        $biz = TestCase::provisionTenant(['name' => 'SMS Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = $this->pageAction->handle($biz->id, 'sms-page', 'SMS Page', false);
        $this->publishAction->handle($biz->id, $page->id, []);

        Http::assertNothingSent();
        Event::assertNotDispatched(SendRequested::class);
    }

    /** [G6-32] (R245) */
    public function test_g6_32_header_x199(): void
    {

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


    public function test_draft_site_action()
    {
        $biz = TestCase::provisionTenant(['name' => 'Draft Site Tenant', 'currency' => 'USD']);
        
        $location = \App\Models\Location::where('business_id', $biz->id)->first();
        if (!$location) {
            $location = \App\Models\Location::factory()->create(['business_id' => $biz->id, 'website_url' => 'https://example.com']);
        } else {
            $location->update(['website_url' => 'https://example.com']);
        }
        
        \App\Support\Tenancy::bindAs($biz->id);

        \App\Modules\X103\Models\SiteInventoryPage::create([
            'business_id' => $biz->id,
            'url' => 'https://example.com',
            'title' => 'Home Page',
            'headings' => ['Welcome to Draft Site Tenant H1'],
            'text' => 'This is the first paragraph. '.str_repeat('A', 150),
            'status' => 'fetched',
            'fetched_at' => now(),
            'phones' => ['555-1234'],
            'emails' => ['hello@example.com'],
        ]);
        
        \App\Modules\X103\Models\SiteInventoryPage::create([
            'business_id' => $biz->id,
            'url' => 'https://example.com/about',
            'title' => 'About Us',
            'text' => 'This is the longest text block. '.str_repeat('B', 1200),
            'status' => 'fetched',
            'fetched_at' => now(),
        ]);
        
        \App\Modules\X103\Models\SiteInventoryImage::create([
            'business_id' => $biz->id,
            'page_id' => 1,
            'status' => 'stored',
            'stored_path' => 'inventory/img1.jpg',
            'attribution' => 'example.com',
        ]);
        
        \App\Modules\X163\Models\PriceBookItem::create([
            'business_id' => $biz->id,
            'slug' => 'service-1',
            'label' => 'Service 1',
            'minor_units' => 10000,
            'currency' => 'USD',
            'source' => \App\Enums\PriceListItemSource::OwnerList,
            'confirmed_at' => now(),
        ]);
        
        \App\Modules\X163\Models\PriceBookItem::create([
            'business_id' => $biz->id,
            'slug' => 'service-2',
            'label' => 'Service 2',
            'minor_units' => 20000,
            'currency' => 'USD',
            'source' => \App\Enums\PriceListItemSource::OwnerList,
            'confirmed_at' => now(),
        ]);
        
        \App\Models\Review::factory()->create([
            'location_id' => $location->id,
            'display_on_website' => true,
            'rating' => 5,
            'comment' => 'Great!',
            'reviewer_name' => 'Alice',
        ]);
        
        \App\Models\Review::factory()->create([
            'location_id' => $location->id,
            'display_on_website' => true,
            'rating' => 2,
            'comment' => 'Bad!',
            'reviewer_name' => 'Bob',
        ]);
        
        \App\Models\TenantLinkRecord::create([
            'business_id' => $biz->id,
            'kind' => \App\Enums\TenantLinkKind::Booking,
            'destination' => 'https://booking.com',
        ]);
        
        $action = app(\App\Modules\X103\Actions\SiteDraftAction::class);
        $res = $action->handle($biz->id, $location->id);
        
        $this->assertEquals(3, $res['pages']);
        
        $home = \App\Modules\X103\Models\Page::where('slug', 'home')->first();
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
        \App\Modules\X163\Models\PriceBookItem::where('business_id', $biz->id)->delete();
        \App\Modules\X103\Models\Page::where('business_id', $biz->id)->delete();
        $res2 = $action->handle($biz->id, $location->id);
        
        $home2 = \App\Modules\X103\Models\Page::where('slug', 'home')->first();
        $types2 = array_column($home2->draft_blocks, 'type');
        $this->assertNotContains('services', $types2);
        
        // no booking link -> no button
        \App\Models\TenantLinkRecord::where('business_id', $biz->id)->delete();
        \App\Modules\X103\Models\Page::where('business_id', $biz->id)->delete();
        $res3 = $action->handle($biz->id, $location->id);
        
        $home3 = \App\Modules\X103\Models\Page::where('slug', 'home')->first();
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
        $publishAction = app(\App\Modules\X103\Actions\SitePublishAction::class);
        // Wait, the prompt says publish through SiteEngine::publish(), which is probably what SitePublishAction uses, or we use SiteEngine directly.
        $engine = app(\App\Modules\X103\Domain\SiteEngine::class);
        $version = $engine->publish($biz->id, clone $home3, $home3->draft_blocks);
        
        // HTML contains escaped headline
        $html = $engine->serve($biz->id, 'home');
        $this->assertStringContainsString(htmlspecialchars('Welcome to Draft Site Tenant H1', ENT_QUOTES, 'UTF-8'), $html);
        Http::assertNothingSent();
    }
}
