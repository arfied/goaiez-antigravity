<?php

declare(strict_types=1);

namespace Tests\Modules\X157;

use App\Modules\X103\Actions\SitePublishAction;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVersion;
use App\Modules\X121\Models\Asset;
use App\Modules\X157\Actions\EdgeDeployAction;
use App\Modules\X157\Actions\EdgeProvisionAction;
use App\Modules\X157\Actions\EdgeRollbackAction;
use App\Modules\X157\Events\DeployCompleted;
use App\Modules\X157\Events\DeployRolledBack;
use App\Modules\X157\Models\Deployment;
use App\Modules\X157\Models\EdgeZone;
use App\Modules\X157\Ui\EdgeStatusPer;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class X157Test extends TestCase
{
    use RefreshesTenantDatabase;

    private EdgeProvisionAction $provisionAction;

    private EdgeDeployAction $deployAction;

    private EdgeRollbackAction $rollbackAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provisionAction = new EdgeProvisionAction;
        $this->deployAction = new EdgeDeployAction;
        $this->rollbackAction = new EdgeRollbackAction;
    }

    /**
     * TEST ANCHOR
     * a site cannot be published without a valid certificate;
     * a deploy failing the speed budget is rolled back automatically and deploy.rolled_back names the metric
     */
    public function test_anchor_ssl_validation_and_automatic_speed_budget_rollback(): void
    {
        Event::fake([DeployCompleted::class, DeployRolledBack::class]);

        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Zone without valid SSL -> deploy write is REFUSED
        $noSslZone = $this->provisionAction->handle($biz->id, 'insecure.tenant.com', false);
        $this->assertFalse($noSslZone->has_valid_ssl);

        $refusedDeploy = $this->deployAction->handle($biz->id, $noSslZone->id);
        $this->assertEquals('refused', $refusedDeploy['status']);
        $this->assertEquals('SSL_CERTIFICATE_REQUIRED', $refusedDeploy['refusal_code']);

        // 2. Zone with valid SSL & compliant TTFB (120ms <= 1500ms budget) -> deploys successfully
        $validSslZone = $this->provisionAction->handle($biz->id, 'acme-hvac.com', true);
        $this->assertTrue($validSslZone->has_valid_ssl);

        $successDeploy = $this->deployAction->handle($biz->id, $validSslZone->id, 120, 1500);
        $this->assertEquals('deployed', $successDeploy['status']);
        Event::assertDispatched(DeployCompleted::class);

        // 3. Deploy failing the speed budget (e.g. 2400ms > 1500ms) is rolled back automatically
        $slowDeploy = $this->deployAction->handle($biz->id, $validSslZone->id, 2400, 1500);
        $this->assertEquals('rolled_back', $slowDeploy['status']);
        $this->assertEquals('ttfb_exceeded_budget', $slowDeploy['metric']);

        $depFresh = Deployment::where('business_id', $biz->id)->find($slowDeploy['deployment_id']);
        $this->assertEquals('rolled_back', $depFresh->status);
        $this->assertStringContainsString('2400ms', $depFresh->rollback_reason);

        Event::assertDispatched(DeployRolledBack::class, function ($event) {
            return $event->metric === 'ttfb_exceeded_budget';
        });
    }

    /**
     * [G6-06] named in the header
     */
    public function test_g6_06_header(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $zone = $this->provisionAction->handle($biz->id, 'fixture-zero-touch.com', true);
        $this->assertEquals('cloudflare', $zone->provider);
        $this->assertStringStartsWith('cf_zone_', $zone->zone_id);
        $this->assertTrue($zone->has_valid_ssl);
        $this->assertNotNull($zone->ssl_certificate_id);
        $this->assertEquals('active', $zone->status);

        $zone2 = $this->provisionAction->handle($biz->id, 'fixture-pending.com', false);
        $this->assertEquals('pending_ssl', $zone2->status);
        $this->assertNull($zone2->ssl_certificate_id);

        $refusedDeploy = $this->deployAction->handle($biz->id, $zone2->id);
        $this->assertEquals('refused', $refusedDeploy['status']);
        $this->assertEquals('SSL_CERTIFICATE_REQUIRED', $refusedDeploy['refusal_code']);
        $this->assertEquals(0, Deployment::where('business_id', $biz->id)->where('edge_zone_id', $zone2->id)->count());

        $zone3 = $this->provisionAction->handle($biz->id, 'fixture-zero-touch.com', true);
        $this->assertEquals(1, EdgeZone::where('business_id', $biz->id)->where('domain_name', 'fixture-zero-touch.com')->count());
    }

    /**
     * [G6-33] Vercel is corpus vocabulary — the edge is Cloudflare (§33.1)
     */
    public function test_g6_33_cloudflare_edge(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'CF Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $zone = $this->provisionAction->handle($biz->id, 'cf-test.com', true);
        $this->assertEquals('cloudflare', $zone->provider);
    }

    /**
     * [G13-31] R2, zero egress; the Asset row is X-121's
     */
    public function test_g13_31_r2_zero_egress(): void
    {
        $this->assertFalse(array_key_exists('r2', config('filesystems.disks')));

        Http::fake();
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $zone = $this->provisionAction->handle($biz->id, 'acme-hvac.com', true);

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $commitId = 'commit_'.Str::random(16);
        PageVersion::create([
            'business_id' => $biz->id,
            'page_id' => $page->id,
            'commit_id' => $commitId,
            'content_blocks' => [],
            'pixel_installed' => false,
        ]);

        $deploy = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $commitId,
            businessName: $biz->name
        );

        Storage::disk('local')->assertExists("sites/{$deploy['deploy_hash']}.html");
        Http::assertNothingSent();

        $this->get("/sites/{$biz->id}/{$deploy['deploy_hash']}")->assertOk();
        Http::assertNothingSent();

        $this->assertFalse(class_exists('App\Modules\X157\Models\Asset'));
        $this->assertTrue(class_exists(Asset::class));
    }

    public function test_feature_flags_absent(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $zone = $this->provisionAction->handle($biz->id, 'acme-hvac.com', true);

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $commitId = 'commit_'.Str::random(16);
        PageVersion::create([
            'business_id' => $biz->id,
            'page_id' => $page->id,
            'commit_id' => $commitId,
            'content_blocks' => [],
            'pixel_installed' => false,
        ]);

        $deploy = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $commitId,
            businessName: $biz->name
        );

        $html = Storage::disk('local')->get("sites/{$deploy['deploy_hash']}.html");

        $this->assertStringNotContainsString('x110-pixel', $html);
        $this->assertStringNotContainsString('chat-widget-container', $html);
        $this->assertStringNotContainsString('form-capture-x155', $html);
        $this->assertStringNotContainsString('dni-pool-x137', $html);
    }

    public function test_feature_flags_present(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $zone = $this->provisionAction->handle($biz->id, 'acme-hvac.com', true);

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $commitId = 'commit_'.Str::random(16);
        PageVersion::create([
            'business_id' => $biz->id,
            'page_id' => $page->id,
            'commit_id' => $commitId,
            'content_blocks' => [
                ['type' => 'chat'],
                ['type' => 'form_capture'],
                ['type' => 'dni'],
            ],
            'pixel_installed' => true,
        ]);

        $deploy = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $commitId,
            businessName: $biz->name
        );

        $html = Storage::disk('local')->get("sites/{$deploy['deploy_hash']}.html");

        $this->assertStringContainsString('x110-pixel', $html);
        $this->assertStringContainsString('chat-widget-container', $html);
        $this->assertStringContainsString('form-capture-x155', $html);
        $this->assertStringContainsString('dni-pool-x137', $html);
    }

    public function test_ssl_guard_writes_no_artifact(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $noSslZone = $this->provisionAction->handle($biz->id, 'insecure.tenant.com', false);

        $refusedDeploy = $this->deployAction->handle($biz->id, $noSslZone->id);

        $this->assertEquals('refused', $refusedDeploy['status']);
        $this->assertEquals('SSL_CERTIFICATE_REQUIRED', $refusedDeploy['refusal_code']);

        $files = Storage::disk('local')->files('sites');
        $this->assertEmpty($files);
    }

    public function test_route_served_body_equals_stored_artifact(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $zone = $this->provisionAction->handle($biz->id, 'acme-hvac.com', true);

        $deploy = Deployment::create([
            'business_id' => $biz->id,
            'edge_zone_id' => $zone->id,
            'deploy_hash' => 'test_hash_1',
            'status' => 'deployed',
            'measured_ttfb_ms' => 100,
            'speed_budget_ms' => 1500,
        ]);

        Storage::disk('local')->put('sites/test_hash_1.html', 'BODY_CONTENT');

        $response = $this->get("/sites/{$biz->id}/test_hash_1");
        $response->assertStatus(200);
        $this->assertEquals('BODY_CONTENT', $response->getContent());
    }

    public function test_route_unknown_hash_returns_404(): void
    {
        $response = $this->get('/sites/999/unknown_hash_999');
        $response->assertStatus(404);
    }

    public function test_route_deployment_whose_zone_lost_ssl_returns_404(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $zone = $this->provisionAction->handle($biz->id, 'acme-hvac.com', false);

        $deploy = Deployment::create([
            'business_id' => $biz->id,
            'edge_zone_id' => $zone->id,
            'deploy_hash' => 'test_hash_no_ssl',
            'status' => 'deployed',
            'measured_ttfb_ms' => 100,
            'speed_budget_ms' => 1500,
        ]);

        Storage::disk('local')->put('sites/test_hash_no_ssl.html', 'BODY_CONTENT');

        $response = $this->get("/sites/{$biz->id}/test_hash_no_ssl");
        $response->assertStatus(404);
    }

    public function test_deployment_exposes_its_edge_zone_and_ssl_state(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $zone = $this->provisionAction->handle($biz->id, 'acme-hvac.com', true);

        $deploy = Deployment::create([
            'business_id' => $biz->id,
            'edge_zone_id' => $zone->id,
            'deploy_hash' => 'test_hash_ssl_read',
            'status' => 'deployed',
            'measured_ttfb_ms' => 100,
            'speed_budget_ms' => 1500,
        ]);

        $this->assertTrue(Deployment::where('deploy_hash', 'test_hash_ssl_read')->first()->edgeZone->has_valid_ssl);

        $zone->update(['has_valid_ssl' => false]);

        $this->assertFalse(Deployment::where('deploy_hash', 'test_hash_ssl_read')->first()->edgeZone->has_valid_ssl);
    }

    public function test_edge_status_per_lists_deployments_with_zone_state(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        $zone = EdgeZone::create([
            'business_id' => $biz->id,
            'domain_name' => 'acme-hvac.com',
            'zone_id' => 'z1',
            'has_valid_ssl' => true,
        ]);

        $d = Deployment::create([
            'business_id' => $biz->id,
            'edge_zone_id' => $zone->id,
            'deploy_hash' => 'hash-12345',
            'status' => 'deployed',
        ]);

        $biz2 = TestCase::provisionTenant(['name' => 'Other Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz2->id);
        $zone2 = EdgeZone::create([
            'business_id' => $biz2->id,
            'domain_name' => 'other.com',
            'zone_id' => 'z2',
        ]);
        Deployment::create([
            'business_id' => $biz2->id,
            'edge_zone_id' => $zone2->id,
            'deploy_hash' => 'hash-other',
            'status' => 'deployed',
        ]);

        Tenancy::set((int) $biz->id);

        Livewire::test(EdgeStatusPer::class, ['businessId' => $biz->id])
            ->assertOk()
            ->assertSee('hash-12345')
            ->assertSee('acme-hvac.com')
            ->assertDontSee('hash-other');
    }

    public function test_edge_status_per_rolls_back_a_deployment(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        $zone = EdgeZone::create([
            'business_id' => $biz->id,
            'domain_name' => 'acme-hvac.com',
            'zone_id' => 'z1',
            'has_valid_ssl' => true,
        ]);

        $d = Deployment::create([
            'business_id' => $biz->id,
            'edge_zone_id' => $zone->id,
            'deploy_hash' => 'hash-12345',
            'status' => 'deployed',
        ]);

        Livewire::test(EdgeStatusPer::class, ['businessId' => $biz->id])
            ->call('rollback', $d->id)
            ->assertOk();

        $d->refresh();
        $this->assertEquals('rolled_back', $d->status);
    }

    public function test_edge_status_per_refuses_another_tenants_deployment(): void
    {
        $bizA = TestCase::provisionTenant(['name' => 'Biz A', 'currency' => 'USD']);
        Tenancy::set((int) $bizA->id);
        $zoneA = EdgeZone::create([
            'business_id' => $bizA->id,
            'domain_name' => 'a.com',
            'zone_id' => 'z_a',
            'has_valid_ssl' => true,
        ]);
        $deploymentA = Deployment::create([
            'business_id' => $bizA->id,
            'edge_zone_id' => $zoneA->id,
            'deploy_hash' => 'hash-a',
            'status' => 'deployed',
        ]);

        $bizB = TestCase::provisionTenant(['name' => 'Biz B', 'currency' => 'USD']);
        Tenancy::set((int) $bizB->id);
        $zoneB = EdgeZone::create([
            'business_id' => $bizB->id,
            'domain_name' => 'b.com',
            'zone_id' => 'z_b',
            'has_valid_ssl' => true,
        ]);
        $deploymentB = Deployment::create([
            'business_id' => $bizB->id,
            'edge_zone_id' => $zoneB->id,
            'deploy_hash' => 'hash-b',
            'status' => 'deployed',
        ]);

        Livewire::test(EdgeStatusPer::class, ['businessId' => $bizA->id])
            ->call('rollback', $deploymentB->id)
            ->assertOk()
            ->assertDontSee('App\Modules\X157\Models\Deployment')
            ->assertSee('That deployment is not available for this business.');

        $this->assertEquals('deployed', $deploymentB->refresh()->status);
    }

    public function test_route_cleared_tenant_returns_404(): void
    {
        Storage::fake('local');
        $bizA = TestCase::provisionTenant(['name' => 'Biz A', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$bizA->id}'");

        $page = Page::create(['business_id' => $bizA->id, 'title' => 'Home', 'slug' => 'home']);
        $site = app(SitePublishAction::class)->handle($bizA->id, $page->id, []);
        $zone = $this->provisionAction->handle($bizA->id, 'acme.com', true);
        $deploy = $this->deployAction->handle($bizA->id, $zone->id, 120, 1500, $page->id, $site['commit_id'], $bizA->name);

        $bizB = TestCase::provisionTenant(['name' => 'Biz B', 'currency' => 'USD']);

        $this->get("/sites/{$bizB->id}/{$deploy['deploy_hash']}")->assertStatus(404);
    }

    public function test_route_cleared_tenant_returns_200_positive(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $site = app(SitePublishAction::class)
            ->handle($biz->id, $page->id, [
                ['type' => 'chat'],
                ['type' => 'form_capture'],
                ['type' => 'dni'],
            ]);

        $zone = $this->provisionAction->handle($biz->id, 'acme-hvac.com', true);

        $deploy = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );

        DB::statement("SELECT set_config('app.business_id', '', true)");
        $this->get("/sites/{$biz->id}/{$deploy['deploy_hash']}")->assertStatus(200);
    }

    /** (R245) */
    public function test_the_published_route_carries_all_seven_elements(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $site = app(SitePublishAction::class)
            ->handle($biz->id, $page->id, [
                ['type' => 'chat'],
                ['type' => 'form_capture'],
                ['type' => 'dni'],
            ]);

        $zone = $this->provisionAction->handle($biz->id, 'acme-hvac.com', true);

        $deploy = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );

        $response = $this->get("/sites/{$biz->id}/{$deploy['deploy_hash']}");
        $response->assertStatus(200);
        $html = (string) $response->getContent();

        $this->assertStringContainsString('x110-pixel', $html);
        $this->assertStringContainsString('chat-widget-container', $html);
        $this->assertStringContainsString('form-capture-x155', $html);
        $this->assertStringContainsString('dni-pool-x137', $html);
        $this->assertStringContainsString('seo-meta-x176', $html);
        $this->assertStringContainsString('application/ld+json', $html);

        $zoneRow = Deployment::where('deploy_hash', $deploy['deploy_hash'])->first()->edgeZone;
        $zoneRow->update(['has_valid_ssl' => false]);
        $withoutSsl = $this->get("/sites/{$biz->id}/{$deploy['deploy_hash']}");
        $withoutSsl->assertStatus(404);
        $zoneRow->update(['has_valid_ssl' => true]);
    }

    /** (R245) */
    public function test_a_site_published_with_no_blocks_still_carries_all_seven(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $site = app(SitePublishAction::class)
            ->handle($biz->id, $page->id, []);

        $zone = $this->provisionAction->handle($biz->id, 'acme-hvac.com', true);

        $deploy = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );

        $response = $this->get("/sites/{$biz->id}/{$deploy['deploy_hash']}");
        $response->assertStatus(200);
        $html = (string) $response->getContent();

        $this->assertStringContainsString('chat-widget-container', $html);
        $this->assertStringContainsString('x110-pixel', $html);
        $this->assertStringContainsString('form-capture-x155', $html);
        $this->assertStringContainsString('dni-pool-x137', $html);
        $this->assertStringContainsString('seo-meta-x176', $html);
        $this->assertStringContainsString('application/ld+json', $html);

        $zoneRow = Deployment::where('deploy_hash', $deploy['deploy_hash'])->first()->edgeZone;
        $zoneRow->update(['has_valid_ssl' => false]);
        $withoutSsl = $this->get("/sites/{$biz->id}/{$deploy['deploy_hash']}");
        $withoutSsl->assertStatus(404);
        $zoneRow->update(['has_valid_ssl' => true]);
    }

    /** (R245) */
    public function test_the_published_route_emits_each_site_law_element_once(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $site = app(SitePublishAction::class)
            ->handle($biz->id, $page->id, [
                ['type' => 'chat'],
                ['type' => 'form_capture'],
                ['type' => 'dni'],
            ]);

        $zone = $this->provisionAction->handle($biz->id, 'acme-hvac.com', true);

        $deploy = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );

        $response = $this->get("/sites/{$biz->id}/{$deploy['deploy_hash']}");
        $response->assertStatus(200);
        $html = (string) $response->getContent();

        $this->assertSame(1, substr_count($html, 'chat-widget-container'));
        $this->assertSame(1, substr_count($html, 'form-capture-x155'));
        $this->assertSame(1, substr_count($html, 'dni-pool-x137'));
        $this->assertSame(1, substr_count($html, 'x110-pixel'));
    }

    /** (R245) */
    public function test_the_published_pixel_tag_names_a_url_this_platform_serves(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $site = app(SitePublishAction::class)
            ->handle($biz->id, $page->id, []);

        $zone = $this->provisionAction->handle($biz->id, 'acme-hvac.com', true);

        $deploy = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );

        $response = $this->get("/sites/{$biz->id}/{$deploy['deploy_hash']}");
        $response->assertStatus(200);
        $html = (string) $response->getContent();

        preg_match('/<script\s+id="x110-pixel"\s+src="([^"]+)"/', $html, $matches);
        $src = $matches[1];

        $this->get($src)->assertOk();
        $this->assertSame(404, $this->get('/pixel.js')->getStatusCode());
    }

    /** (R245) */
    public function test_the_published_schema_url_and_canonical_name_the_same_page(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $site = app(SitePublishAction::class)
            ->handle($biz->id, $page->id, []);

        $zone = $this->provisionAction->handle($biz->id, 'acme-hvac.com', true);

        $deploy = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );

        $response = $this->get("/sites/{$biz->id}/{$deploy['deploy_hash']}");
        $response->assertStatus(200);
        $html = (string) $response->getContent();

        $this->assertSame(1, preg_match('/<link rel="canonical" href="([^"]+)"/', $html, $c));
        $this->assertSame(1, preg_match('#<script type="application/ld\+json">\s*(\{.*?\})\s*</script>#s', $html, $j));
        $ld = json_decode($j[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($ld);
        $this->assertSame($c[1], $ld['url']);
        $this->assertStringNotContainsString("/pages/{$page->id}", $ld['url']);
    }

    /** (R245) */
    public function test_a_tenant_name_cannot_close_an_element_in_the_published_document(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Acme </script><script>alert(1)</script> HVAC', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $site = app(SitePublishAction::class)
            ->handle($biz->id, $page->id, []);

        $zone = $this->provisionAction->handle($biz->id, 'acme-hvac.com', true);

        $deploy = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );

        $response = $this->get("/sites/{$biz->id}/{$deploy['deploy_hash']}");
        $response->assertStatus(200);
        $html = (string) $response->getContent();

        $this->assertSame(substr_count($html, '<script'), substr_count($html, '</script>'));
        $this->assertSame(1, preg_match('#<script type="application/ld\+json">\s*(\{.*?\})\s*</script>#s', $html, $j));
        $ld = json_decode($j[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($biz->name, $ld['name']);
    }


    public function test_serving_a_published_site_resolves_the_tenant_through_the_chokepoint(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Acme HVAC', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $site = app(SitePublishAction::class)
            ->handle($biz->id, $page->id, []);

        $zone = $this->provisionAction->handle($biz->id, 'acme-hvac.com', true);

        $deploy = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );

        Tenancy::forgetAll();

        $response = $this->get("/sites/{$biz->id}/{$deploy['deploy_hash']}");
        $response->assertStatus(200);

        $this->assertSame(
            (string) $biz->id,
            DB::connection('pgsql')->selectOne("SELECT current_setting('app.business_id', true) AS v")->v
        );

        $this->assertSame($biz->id, Tenancy::id());
    }
    /** (R245) */
    public function test_route_deployment_whose_artifact_is_missing_returns_404(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $site = app(SitePublishAction::class)
            ->handle($biz->id, $page->id, [
                ['type' => 'chat'],
                ['type' => 'form_capture'],
                ['type' => 'dni'],
            ]);

        $zone = $this->provisionAction->handle($biz->id, 'acme-hvac.com', true);

        $deploy = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );

        $response = $this->get("/sites/{$biz->id}/{$deploy['deploy_hash']}");
        $response->assertStatus(200);
        $this->assertStringContainsString('dni-pool-x137', (string) $response->getContent());

        Storage::disk('local')->delete("sites/{$deploy['deploy_hash']}.html");
        $this->assertFalse(Storage::disk('local')->exists("sites/{$deploy['deploy_hash']}.html"));

        $missing = $this->get("/sites/{$biz->id}/{$deploy['deploy_hash']}");
        $missing->assertStatus(404);
    }

    /** (R245) */
    public function test_route_rolled_back_deployment_returns_404(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $site = app(SitePublishAction::class)
            ->handle($biz->id, $page->id, [
                ['type' => 'chat'],
                ['type' => 'form_capture'],
                ['type' => 'dni'],
            ]);

        $zone = $this->provisionAction->handle($biz->id, 'acme-hvac.com', true);

        $deploy = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );

        $response = $this->get("/sites/{$biz->id}/{$deploy['deploy_hash']}");
        $response->assertStatus(200);
        $this->assertStringContainsString('dni-pool-x137', (string) $response->getContent());

        $deployment = Deployment::where('deploy_hash', $deploy['deploy_hash'])->firstOrFail();
        app(EdgeRollbackAction::class)->handle($biz->id, $deployment->id);
        $this->assertEquals('rolled_back', $deployment->refresh()->status);

        $this->assertTrue(Storage::disk('local')->exists("sites/{$deploy['deploy_hash']}.html"));

        $this->get("/sites/{$biz->id}/{$deploy['deploy_hash']}")->assertStatus(404);
    }

    /** (R245) */
    public function test_route_superseded_deployment_returns_404(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $site = app(SitePublishAction::class)
            ->handle($biz->id, $page->id, [
                ['type' => 'chat'],
                ['type' => 'form_capture'],
                ['type' => 'dni'],
            ]);

        $zone = $this->provisionAction->handle($biz->id, 'acme-hvac.com', true);

        $first = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );

        $this->get("/sites/{$biz->id}/{$first['deploy_hash']}")
            ->assertStatus(200)
            ->assertSee('dni-pool-x137', false);

        $second = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );

        $this->assertNotEquals($first['deploy_hash'], $second['deploy_hash']);

        $this->get("/sites/{$biz->id}/{$second['deploy_hash']}")
            ->assertStatus(200)
            ->assertSee('dni-pool-x137', false);

        $this->assertTrue(Storage::disk('local')->exists("sites/{$first['deploy_hash']}.html"));
        $this->assertTrue(Storage::disk('local')->exists("sites/{$second['deploy_hash']}.html"));

        $this->get("/sites/{$biz->id}/{$first['deploy_hash']}")->assertStatus(404);
    }

    /** (R245) */
    public function test_rollback_restores_the_superseded_predecessor(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $site = app(SitePublishAction::class)
            ->handle($biz->id, $page->id, [
                ['type' => 'chat'],
                ['type' => 'form_capture'],
                ['type' => 'dni'],
            ]);

        $zone = $this->provisionAction->handle($biz->id, 'acme-hvac.com', true);

        $first = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );
        $aRow = Deployment::where('deploy_hash', $first['deploy_hash'])->firstOrFail();

        $this->get("/sites/{$biz->id}/{$first['deploy_hash']}")
            ->assertStatus(200)
            ->assertSee('dni-pool-x137', false);

        $second = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );

        $this->assertNotEquals($first['deploy_hash'], $second['deploy_hash']);
        $bRow = Deployment::where('deploy_hash', $second['deploy_hash'])->firstOrFail();

        $this->get("/sites/{$biz->id}/{$first['deploy_hash']}")->assertStatus(404);
        $this->get("/sites/{$biz->id}/{$second['deploy_hash']}")
            ->assertStatus(200)
            ->assertSee('dni-pool-x137', false);

        app(EdgeRollbackAction::class)->handle($biz->id, $bRow->id);

        $this->get("/sites/{$biz->id}/{$second['deploy_hash']}")->assertStatus(404);

        $this->get("/sites/{$biz->id}/{$first['deploy_hash']}")
            ->assertStatus(200)
            ->assertSee('dni-pool-x137', false);

        $this->assertEquals('deployed', $aRow->refresh()->status);
    }

    /** (R245) */
    public function test_rollback_of_a_superseded_deployment_promotes_nothing(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $site = app(SitePublishAction::class)
            ->handle($biz->id, $page->id, [
                ['type' => 'chat'],
                ['type' => 'form_capture'],
                ['type' => 'dni'],
            ]);

        $zone = $this->provisionAction->handle($biz->id, 'acme-hvac.com', true);

        $first = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );
        $aRow = Deployment::where('deploy_hash', $first['deploy_hash'])->firstOrFail();

        $second = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );
        $bRow = Deployment::where('deploy_hash', $second['deploy_hash'])->firstOrFail();

        app(EdgeRollbackAction::class)->handle($biz->id, $aRow->id);

        $this->get("/sites/{$biz->id}/{$second['deploy_hash']}")
            ->assertStatus(200)
            ->assertSee('dni-pool-x137', false);
        $this->assertEquals('deployed', $bRow->refresh()->status);

        $this->get("/sites/{$biz->id}/{$first['deploy_hash']}")->assertStatus(404);
        $this->assertEquals('rolled_back', $aRow->refresh()->status);
    }

    public function test_rollback_of_a_superseded_deployment_does_not_promote_an_older_one(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $site = app(SitePublishAction::class)
            ->handle($biz->id, $page->id, [
                ['type' => 'chat'],
                ['type' => 'form_capture'],
                ['type' => 'dni'],
            ]);

        $zone = $this->provisionAction->handle($biz->id, 'acme-hvac.com', true);

        $first = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );
        $aRow = Deployment::where('deploy_hash', $first['deploy_hash'])->firstOrFail();

        $second = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );
        $bRow = Deployment::where('deploy_hash', $second['deploy_hash'])->firstOrFail();

        $third = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );
        $cRow = Deployment::where('deploy_hash', $third['deploy_hash'])->firstOrFail();

        $this->assertCount(3, array_unique([$first['deploy_hash'], $second['deploy_hash'], $third['deploy_hash']]));

        $this->assertEquals('superseded', $aRow->refresh()->status);
        $this->assertEquals('superseded', $bRow->refresh()->status);
        $this->assertEquals('deployed', $cRow->refresh()->status);

        app(EdgeRollbackAction::class)->handle($biz->id, $bRow->id);

        $this->get("/sites/{$biz->id}/{$first['deploy_hash']}")->assertStatus(404);
        $this->assertEquals('superseded', $aRow->refresh()->status);

        $this->get("/sites/{$biz->id}/{$third['deploy_hash']}")
            ->assertStatus(200)
            ->assertSee('dni-pool-x137', false);
        $this->assertEquals('deployed', $cRow->refresh()->status);

        $this->get("/sites/{$biz->id}/{$second['deploy_hash']}")->assertStatus(404);
        $this->assertEquals('rolled_back', $bRow->refresh()->status);
    }

    public function test_a_zone_has_exactly_one_live_deployment_through_a_rollback_cycle(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $site = app(SitePublishAction::class)
            ->handle($biz->id, $page->id, [
                ['type' => 'chat'],
                ['type' => 'form_capture'],
                ['type' => 'dni'],
            ]);

        $zone = $this->provisionAction->handle($biz->id, 'acme-hvac.com', true);

        $live = fn () => Deployment::where('business_id', $biz->id)
            ->where('edge_zone_id', $zone->id)
            ->where('status', 'deployed')
            ->count();

        $first = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );
        $aRow = Deployment::where('deploy_hash', $first['deploy_hash'])->firstOrFail();
        $this->assertEquals(1, $live());

        $second = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );
        $bRow = Deployment::where('deploy_hash', $second['deploy_hash'])->firstOrFail();
        $this->assertEquals(1, $live());

        app(EdgeRollbackAction::class)->handle($biz->id, $bRow->id);
        $this->assertEquals(1, $live());
        $this->get("/sites/{$biz->id}/{$first['deploy_hash']}")
            ->assertStatus(200)
            ->assertSee('dni-pool-x137', false);

        $third = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );
        $cRow = Deployment::where('deploy_hash', $third['deploy_hash'])->firstOrFail();
        $this->assertEquals(1, $live());
        $this->get("/sites/{$biz->id}/{$first['deploy_hash']}")->assertStatus(404);

        app(EdgeRollbackAction::class)->handle($biz->id, $cRow->id);
        $this->assertEquals(1, $live());
        $this->get("/sites/{$biz->id}/{$first['deploy_hash']}")
            ->assertStatus(200)
            ->assertSee('dni-pool-x137', false);
    }
}
