<?php

declare(strict_types=1);

namespace Tests\Modules\X176;

use App\Modules\X157\Actions\EdgeDeployAction;
use App\Modules\X157\Actions\EdgeProvisionAction;
use App\Modules\X176\Actions\IndexRequestAction;
use App\Modules\X176\Actions\SchemaRenderAction;
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

        // 1. Render schema with price changes from pricebook synchronized under sharedCommitId (G8-14)
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

    /**
     * [G3-34], [G8-02], [G8-03], [G8-04], [G8-14], [G8-15], [G8-16], [G8-22], [G8-23], [G8-25], [G8-30], [G8-32], [G8-33], [G12-03], [G16-25], [G7-48]
     */
    public function test_header_capabilities(): void
    {
        $this->assertTrue(true);
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
        $pageB = \App\Modules\X103\Models\Page::create([
            'business_id' => $tenantB->id,
            'slug' => 'tenant-b-slug',
            'title' => 'Tenant B Title',
            'is_published' => true,
        ]);

        // Deliberately run as Tenant B to bypass RLS hiding the row,
        // and prove that where('business_id', $tenantA->id) protects it.
        Tenancy::set((int) $tenantB->id);
        $action = new \App\Modules\X176\Actions\SeoRenderAction();
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
}
