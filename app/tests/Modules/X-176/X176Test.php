<?php

declare(strict_types=1);

namespace Tests\Modules\X176;

use App\Modules\X103\Models\Page;
use App\Modules\X157\Actions\EdgeDeployAction;
use App\Modules\X157\Actions\EdgeProvisionAction;
use App\Modules\X176\Actions\IndexRequestAction;
use App\Modules\X176\Actions\SchemaRenderAction;
use App\Modules\X176\Actions\SeoRenderAction;
use App\Modules\X176\Actions\SitemapPingAction;
use App\Modules\X176\Events\IndexRequested;
use App\Modules\X176\Events\SchemaPublished;
use App\Modules\X176\Models\SchemaSnapshot;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class X176Test extends TestCase
{
    private SchemaRenderAction $renderAction;

    private IndexRequestAction $indexAction;

    private SitemapPingAction $sitemapAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderAction = new SchemaRenderAction;
        $this->indexAction = new IndexRequestAction;
        $this->sitemapAction = new SitemapPingAction;
    }

    /**
     * TEST ANCHOR
     * every published page validates against schema.org with zero errors in CI;
     * a price change updates the page's JSON-LD in the same commit as the Fact
     */
    public function test_anchor_schema_validation_and_price_fact_commit_synchronization(): void
    {
        Event::fake([SchemaPublished::class, IndexRequested::class]);

        $biz = TestCase::provisionTenant(['name' => 'SEO Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        $sharedCommitId = 'commit_price_change_9901';

        // 1. Render schema with price changes from pricebook synchronized under sharedCommitId
        $res = $this->renderAction->handle(
            businessId: $biz->id,
            pageId: 101,
            businessName: 'Apex HVAC & Plumbing',
            commitId: $sharedCommitId,
            domainName: 'seo.com',
            entityType: 'Plumber',
            productOffers: [
                ['name' => 'Drain Clearing', 'price' => '99.00'],
                ['name' => 'Water Heater Install', 'price' => '1200.00'],
            ]
        );

        $this->assertEquals('published', $res['status']);
        $this->assertEquals($sharedCommitId, $res['commit_id']);
        $this->assertEquals('https://schema.org', $res['json_ld']['@context']);
        $this->assertEquals('Plumber', $res['json_ld']['@type']);
        $this->assertArrayHasKey('hasOfferCatalog', $res['json_ld']);

        $snapshot = SchemaSnapshot::where('business_id', $biz->id)->find($res['snapshot_id']);
        $this->assertTrue($snapshot->is_valid_schema, 'Schema must validate with zero errors in CI (TEST ANCHOR, G8-32)');
        $this->assertEquals($sharedCommitId, $snapshot->commit_id);

        Event::assertDispatched(SchemaPublished::class);

        // 2. Index request & sitemap ping
        $indexRes = $this->indexAction->handle($biz->id, 'https://example.com/pages/101');
        $this->assertEquals('indexing_requested', $indexRes['status']);
        Event::assertDispatched(IndexRequested::class);

        $sitemapRes = $this->sitemapAction->handle($biz->id, 'https://example.com/sitemap.xml');
        $this->assertEquals('pinged', $sitemapRes['status']);
    }

    /** (R245) */
    public function test_offer_catalog_renders_zero_priced_offers(): void
    {
        // product schema from the pricebook (delegates to X-163/X-119, but we just assert the shape here)
        $biz = TestCase::provisionTenant(['name' => 'SEO Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);
        $res = $this->renderAction->handle(
            businessId: $biz->id, pageId: 101, businessName: 'SEO', commitId: 'c123', domainName: 'seo.com', entityType: 'Plumber',
            productOffers: [['name' => 'Drain Clearing', 'price' => '99.00']]
        );
        $this->assertArrayHasKey('hasOfferCatalog', $res['json_ld']);
        $this->assertEquals('c123', $res['commit_id']);

        $resFree = $this->renderAction->handle(
            businessId: $biz->id, pageId: 101, businessName: 'SEO', commitId: 'c123', domainName: 'seo.com', entityType: 'Plumber',
            productOffers: [
                ['name' => 'Free Callout', 'price' => '0.00'],
                ['name' => 'Zero String', 'price' => '0'],
                ['name' => 'Zero Integer', 'price' => 0],
            ]
        );
        $this->assertEquals('published', $resFree['status']);
        $this->assertArrayHasKey('hasOfferCatalog', $resFree['json_ld']);
        $this->assertCount(3, $resFree['json_ld']['hasOfferCatalog']['itemListElement']);
    }

    /** (R245) */
    public function test_render_omits_event_key_when_no_calendar_source(): void
    {
        // Delegates to X-108
        $this->assertTrue(is_dir(app_path('Modules/X-108')));

        $biz = TestCase::provisionTenant(['name' => 'Calendar Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        $res = $this->renderAction->handle(
            businessId: $biz->id, pageId: 101, businessName: 'Calendar', commitId: 'e1', domainName: 'calendar.com'
        );
        $this->assertSame('published', $res['status']);
        $this->assertArrayNotHasKey('event', $res['json_ld']);
    }

    /**
     * [G8-32]
     */
    public function test_g8_32_capabilities(): void
    {

        $biz = TestCase::provisionTenant(['name' => 'SEO Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        $res1 = $this->renderAction->handle(
            businessId: $biz->id, pageId: 101, businessName: 'SEO', commitId: 'c123', domainName: 'seo.com'
        );
        $this->assertEquals('published', $res1['status']);
        $this->assertEquals('https://schema.org', $res1['json_ld']['@context'] ?? null);

        $snapshot = SchemaSnapshot::where('business_id', $biz->id)->where('page_id', 101)->first();
        $this->assertTrue($snapshot->is_valid_schema);

        $res2 = $this->renderAction->handle(
            businessId: $biz->id, pageId: 101, businessName: '', commitId: 'c124', domainName: 'seo.com'
        );
        $this->assertEquals('refused', $res2['status']);
        $this->assertEquals('SCHEMA_INVALID', $res2['refusal_code']);
        $this->assertFalse(array_key_exists('json_ld', $res2));

        $reflection = new \ReflectionClass($this->renderAction);
        $method = $reflection->getMethod('validateSchema');
        $method->setAccessible(true);
        $this->assertFalse($method->invoke($this->renderAction, [
            '@context' => 'http://bad.org',
            '@type' => 'LocalBusiness',
            'name' => 'SEO',
            'url' => 'https://seo.com/pages/101',
        ]));
        $this->assertFalse($method->invoke($this->renderAction, [
            '@context' => 'https://schema.org', '@type' => 'LocalBusiness', 'name' => 'SEO', 'url' => 'https://seo.com',
            'hasOfferCatalog' => ['@type' => 'OfferCatalog', 'itemListElement' => [['@type' => 'Thing']]],
        ]));
    }

    /**
     * [G12-03]
     */
    public function test_g12_03_capabilities(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'SEO Tenant', 'currency' => 'USD']);
        $biz->vertical = 'hvac';
        $biz->save();
        Tenancy::set((int) $biz->id);
        $res = $this->renderAction->handle(
            businessId: $biz->id, pageId: 101, businessName: 'SEO', commitId: 'c123', domainName: 'seo.com'
        );
        $this->assertEquals('HVACBusiness', $res['json_ld']['@type'] ?? null);

        $biz->vertical = null;
        $biz->save();
        $res2 = $this->renderAction->handle(
            businessId: $biz->id, pageId: 101, businessName: 'SEO', commitId: 'c123', domainName: 'seo.com'
        );
        $this->assertEquals('LocalBusiness', $res2['json_ld']['@type'] ?? null);
    }

    /**
     * [G16-25]
     */
    public function test_g16_25_capabilities(): void
    {

        $biz = TestCase::provisionTenant(['name' => 'Video Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        $res = $this->renderAction->handle(
            businessId: $biz->id, pageId: 101, businessName: 'Video', commitId: 'v1', domainName: 'video.com',
            videos: [['name' => 'Drain Clearing Explained', 'contentUrl' => 'https://video.com/drain.mp4', 'uploadDate' => '2026-09-01']]
        );
        $this->assertSame('published', $res['status']);
        $this->assertSame('VideoObject', $res['json_ld']['video'][0]['@type'] ?? null);
        $this->assertSame('https://video.com/drain.mp4', $res['json_ld']['video'][0]['contentUrl'] ?? null);

        $bad = $this->renderAction->handle(
            businessId: $biz->id, pageId: 102, businessName: 'Video', commitId: 'v2', domainName: 'video.com',
            videos: [['name' => 'Broken', 'uploadDate' => '2026-09-01']]
        );
        $this->assertSame('refused', $bad['status']);
        $this->assertSame('SCHEMA_INVALID', $bad['refusal_code']);
    }

    /**
     * [G7-48]
     */
    public function test_g7_48_capabilities(): void
    {

        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'SEO Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'seo.com', true);

        $deployValid = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: 101,
            commitId: 'c123',
            businessName: 'Valid Name'
        );
        $htmlValid = Storage::disk('local')->get("sites/{$deployValid['deploy_hash']}.html");
        $this->assertStringContainsString('application/ld+json', $htmlValid);

        $deployRefused = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: 101,
            commitId: 'c124',
            businessName: ''
        );
        $htmlRefused = Storage::disk('local')->get("sites/{$deployRefused['deploy_hash']}.html");
        $this->assertStringNotContainsString('application/ld+json', $htmlRefused);
    }

    public function test_seo_block_present_and_absent(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'SEO Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'seo.com', true);

        $deployLegacy = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500
        );
        $htmlLegacy = Storage::disk('local')->get("sites/{$deployLegacy['deploy_hash']}.html");
        $this->assertStringNotContainsString('id="seo-meta-x176"', $htmlLegacy);

        $deployNew = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: 101,
            commitId: 'c123',
            businessName: 'SEO Tenant'
        );
        $htmlNew = Storage::disk('local')->get("sites/{$deployNew['deploy_hash']}.html");

        $this->assertStringContainsString('id="seo-meta-x176"', $htmlNew);
        $this->assertStringContainsString('<title id="seo-meta-x176">SEO Tenant</title>', $htmlNew);
        $this->assertStringContainsString('<meta name="description" content="SEO Tenant">', $htmlNew);
        $this->assertStringContainsString('<link rel="canonical" href="https://seo.com/pages/101">', $htmlNew);
        $this->assertStringNotContainsString('example.com', $htmlNew);

        // Test escaping
        $deployEscaped = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: 101,
            commitId: 'c123',
            businessName: 'SEO & "Co"'
        );
        $htmlEscaped = Storage::disk('local')->get("sites/{$deployEscaped['deploy_hash']}.html");

        $this->assertStringContainsString('<title id="seo-meta-x176">SEO &amp; &quot;Co&quot;</title>', $htmlEscaped);
        $this->assertStringContainsString('<meta name="description" content="SEO &amp; &quot;Co&quot;">', $htmlEscaped);
    }

    public function test_seo_never_reads_another_tenants_page(): void
    {
        $tenantA = TestCase::provisionTenant(['name' => 'Tenant A', 'currency' => 'USD']);
        $tenantB = TestCase::provisionTenant(['name' => 'Tenant B', 'currency' => 'USD']);

        Tenancy::set((int) $tenantB->id);
        $pageB = Page::create([
            'business_id' => $tenantB->id,
            'slug' => 'tenant-b-slug',
            'title' => 'Tenant B Title',
            'is_published' => true,
        ]);

        // Deliberately run as Tenant B to bypass RLS hiding the row,
        // and prove that where('business_id', $tenantA->id) protects it.
        Tenancy::set((int) $tenantB->id);
        $action = new SeoRenderAction;
        $res = $action->handle(
            businessId: $tenantA->id,
            pageId: $pageB->id,
            businessName: 'Tenant A',
            commitId: 'c1',
            domainName: 'seo-a.com'
        );

        $this->assertEquals('Tenant A', $res['title']);
        $this->assertEquals("https://seo-a.com/pages/{$pageB->id}", $res['canonical']);
    }

    public function test_x176_capabilities(): void
    {
        $caps = require app_path('Modules/X-176/capabilities.php');
        $this->assertArrayHasKey('G3-34', $caps);
        $this->assertArrayHasKey('G8-02', $caps);
        $this->assertArrayHasKey('G8-03', $caps);
        $this->assertArrayHasKey('G8-04', $caps);
        $this->assertArrayHasKey('G8-14', $caps);
        $this->assertArrayHasKey('G8-15', $caps);
        $this->assertArrayHasKey('G8-16', $caps);
        $this->assertArrayHasKey('G8-22', $caps);
        $this->assertArrayHasKey('G8-23', $caps);
        $this->assertArrayHasKey('G8-25', $caps);
        $this->assertArrayHasKey('G8-30', $caps);
        $this->assertArrayHasKey('G8-32', $caps);
        $this->assertArrayHasKey('G8-33', $caps);
        $this->assertArrayHasKey('G12-03', $caps);
        $this->assertArrayHasKey('G16-25', $caps);
        $this->assertArrayHasKey('G7-48', $caps);
    }
}
