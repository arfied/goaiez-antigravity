<?php

declare(strict_types=1);

namespace Tests\Modules\X157;

use App\Models\Business;
use App\Models\User;
use App\Modules\X103\Actions\SitePublishAction;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVersion;
use App\Modules\X121\Models\Asset;
use App\Modules\X121\Models\Person;
use App\Modules\X155\Actions\FormCreateAction;
use App\Modules\X155\Models\FormDefinition;
use App\Modules\X155\Models\FormSubmission;
use App\Modules\X157\Actions\CustomDomainVerifyAction;
use App\Modules\X157\Actions\EdgeDeployAction;
use App\Modules\X157\Actions\EdgeProvisionAction;
use App\Modules\X157\Actions\EdgeRollbackAction;
use App\Modules\X157\Actions\PlatformSiteAddressAction;
use App\Modules\X157\Actions\SitemapRenderAction;
use App\Modules\X157\Domain\DnsResolver;
use App\Modules\X157\Events\DeployCompleted;
use App\Modules\X157\Events\DeployRolledBack;
use App\Modules\X157\Models\CustomDomainRequest;
use App\Modules\X157\Models\Deployment;
use App\Modules\X157\Models\EdgeZone;
use App\Modules\X157\Ui\EdgeStatusPer;
use App\Services\Pixel\PixelKeys;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
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
        app()->instance(DnsResolver::class, new class implements DnsResolver
        {
            public function cname(string $domain): ?string
            {
                if ($domain === 'acme-roofing.test') {
                    return parse_url(config('app.url'), PHP_URL_HOST);
                }
                if ($domain === 'missing.test') {
                    return null;
                }
                if ($domain === 'other.test') {
                    return 'other.com';
                }

                return null;
            }
        });
        $this->provisionAction = new EdgeProvisionAction;
        $this->deployAction = app(EdgeDeployAction::class);
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

    public function test_a_flag_column_alone_does_not_put_a_marker_on_the_page(): void
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
            'chat_installed' => true,
            'form_capture_installed' => true,
            'dni_installed' => true,
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

        $this->assertStringNotContainsString('chat-widget-container', $html);
        $this->assertStringNotContainsString('form-capture-x155', $html);
        $this->assertStringNotContainsString('dni-pool-x137', $html);
    }

    public function test_the_pixel_flag_alone_does_not_put_a_pixel_on_the_page(): void
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

        $this->assertStringNotContainsString('x110-pixel', $html);
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
                ['type' => 'pixel_script'],
                ['type' => 'chat_widget'],
                ['type' => 'form_capture'],
                ['type' => 'dni_script'],
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
                ['type' => 'hero', 'headline' => 'Hero', 'image_path' => '/some-image.jpg'],
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

        $this->assertStringStartsWith('<!doctype html><html lang="en"><head>', ltrim($html));
        $this->assertStringContainsString('<meta name="viewport" content="width=device-width, initial-scale=1">', $html);
        $this->assertStringContainsString('x110-pixel', $html);
        $this->assertStringContainsString('chat-widget-container', $html);
        $this->assertStringContainsString('form-capture-x155', $html);
        $this->assertStringContainsString('dni-pool-x137', $html);
        $this->assertStringContainsString('seo-meta-x176', $html);
        $this->assertStringContainsString('application/ld+json', $html);
        $this->assertStringContainsString('<img src="/sites/'.$biz->id.'/'.$deploy['deploy_hash'].'/media/some-image.jpg"', $html);

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

    public function test_publishing_a_page_deploys_it_to_the_businesses_active_zone(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $zone = $this->provisionAction->handle($biz->id, 'acme-hvac.com', true);

        $site = app(SitePublishAction::class)->handle($biz->id, $page->id, [
            ['type' => 'chat'], ['type' => 'form_capture'], ['type' => 'dni'],
        ]);

        $deployment = Deployment::where('business_id', $biz->id)->where('edge_zone_id', $zone->id)->firstOrFail();
        $this->assertSame('deployed', $deployment->status);

        $response = $this->get("/sites/{$biz->id}/{$deployment->deploy_hash}");
        $this->assertSame(200, $response->getStatusCode());

        $html = (string) $response->getContent();
        $this->assertSame(1, preg_match('#<script type="application/ld\+json">\s*(\{.*?\})\s*</script>#s', $html, $j));
        $ld = json_decode($j[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($biz->name, $ld['name']);
    }

    public function test_publishing_without_an_edge_zone_publishes_and_deploys_nothing(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $site = app(SitePublishAction::class)->handle($biz->id, $page->id, [
            ['type' => 'chat'], ['type' => 'form_capture'], ['type' => 'dni'],
        ]);

        $this->assertTrue($page->fresh()->is_published);
        $this->assertSame(0, Deployment::where('business_id', $biz->id)->count());
    }

    public function test_a_failed_deploy_leaves_the_page_published(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $this->provisionAction->handle($biz->id, 'acme-hvac.com', true);

        Event::listen(DeployCompleted::class, function (): void {
            throw new \RuntimeException('edge storage unavailable');
        });

        app(SitePublishAction::class)->handle($biz->id, $page->id, [['type' => 'chat'], ['type' => 'form_capture'], ['type' => 'dni']]);

        $this->assertTrue($page->fresh()->is_published);
        $this->assertNotNull($page->fresh()->current_version_id);
        $this->assertSame(0, Deployment::where('business_id', $biz->id)->count());
    }

    public function test_nothing_learns_of_a_deploy_before_its_artifact_exists(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $this->provisionAction->handle($biz->id, 'acme-hvac.com', true);

        $artifactExistedAtDispatch = null;

        Event::listen(DeployCompleted::class, function (DeployCompleted $event) use (&$artifactExistedAtDispatch): void {
            $artifactExistedAtDispatch = Storage::disk('local')->exists("sites/{$event->deployHash}.html");
        });

        app(SitePublishAction::class)->handle($biz->id, $page->id, [['type' => 'chat'], ['type' => 'form_capture'], ['type' => 'dni']]);

        $this->assertNotNull($artifactExistedAtDispatch, 'the DeployCompleted listener never ran');
        $this->assertTrue($artifactExistedAtDispatch);

        $deployment = Deployment::where('business_id', $biz->id)->firstOrFail();
        $this->assertSame('deployed', $deployment->status);
        $this->assertTrue(Storage::disk('local')->exists("sites/{$deployment->deploy_hash}.html"));
    }

    public function test_a_deploy_whose_artifact_cannot_be_written_is_not_announced(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $this->provisionAction->handle($biz->id, 'acme-hvac.com', true);

        Storage::disk('local')->makeDirectory('sites');

        $sitesDir = Storage::disk('local')->path('sites');
        $this->assertDirectoryExists($sitesDir);

        chmod($sitesDir, 0500);
        clearstatcache();

        try {
            $this->assertFalse(
                Storage::disk('local')->put('sites/probe.html', 'x'),
                'the sabotage did not make sites/ unwritable — this test proves nothing'
            );

            $announced = false;

            Event::listen(DeployCompleted::class, function () use (&$announced): void {
                $announced = true;
            });

            app(SitePublishAction::class)->handle($biz->id, $page->id, [['type' => 'chat'], ['type' => 'form_capture'], ['type' => 'dni']]);

            $this->assertFalse($announced, 'DeployCompleted fired for a deploy whose artifact was never written');
            $this->assertSame(0, Deployment::where('business_id', $biz->id)->count());
            $this->assertTrue($page->fresh()->is_published);
        } finally {
            chmod($sitesDir, 0700);
        }
    }

    public function test_a_deploy_missing_its_seo_half_is_not_announced(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $zone = $this->provisionAction->handle($biz->id, 'acme-hvac.com', true);

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
            'is_published' => true,
        ]);

        $commitId = 'commit_'.Str::random(16);
        PageVersion::create([
            'business_id' => $biz->id,
            'page_id' => $page->id,
            'commit_id' => $commitId,
            'content_blocks' => [['type' => 'chat_widget'], ['type' => 'form_capture'], ['type' => 'dni_script']],
            'pixel_installed' => true,
        ]);

        $announced = false;

        Event::listen(DeployCompleted::class, function () use (&$announced): void {
            $announced = true;
        });

        $good = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $commitId,
            businessName: $biz->name
        );

        $this->assertSame('deployed', $good['status'], 'the control deploy did not succeed — this test proves nothing');
        $this->assertTrue($announced, 'the control deploy was not announced — this test proves nothing');

        $goodHtml = Storage::disk('local')->get("sites/{$good['deploy_hash']}.html");
        $this->assertStringContainsString('seo-meta-x176', $goodHtml);
        $this->assertStringContainsString('application/ld+json', $goodHtml);
        $this->assertStringContainsString('chat-widget-container', $goodHtml);

        $announced = false;

        try {
            $this->deployAction->handle(
                businessId: $biz->id,
                edgeZoneId: $zone->id,
                measuredTtfbMs: 120,
                speedBudgetMs: 1500,
                pageId: $page->id,
                commitId: $commitId,
                businessName: null
            );
        } catch (\RuntimeException) {
            // the refusal; the four assertions below are the claim, not the exception
        }

        $this->assertFalse($announced, 'DeployCompleted fired for a site published without its SEO half');
        $this->assertSame(1, Deployment::where('business_id', $biz->id)->count());
        $this->assertSame(
            ["sites/{$good['deploy_hash']}.html", "sites/{$good['deploy_hash']}.llms.txt"],
            Storage::disk('local')->files('sites'),
            'a second artifact was written for a deploy carrying four of the seven elements'
        );
        $this->assertSame('deployed', Deployment::where('business_id', $biz->id)->sole()->status);
    }

    public function test_the_listener_publishes_all_seven_on_a_real_request(): void
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

        $site = app(SitePublishAction::class)->handle($biz->id, $page->id, [
            ['type' => 'chat'],
            ['type' => 'form_capture'],
            ['type' => 'dni'],
        ]);

        $this->assertSame('published', $site['status']);

        $deployment = Deployment::where('business_id', $biz->id)
            ->where('status', 'deployed')
            ->sole();

        $this->assertSame($zone->id, $deployment->edge_zone_id, 'the listener did not deploy to the provisioned zone');

        $response = $this->get("/sites/{$biz->id}/{$deployment->deploy_hash}");
        $response->assertStatus(200);
        $html = (string) $response->getContent();

        $this->assertStringContainsString('x110-pixel', $html, 'the listener deploy is missing the pixel');
        $this->assertStringContainsString('chat-widget-container', $html, 'the listener deploy is missing the chat marker');
        $this->assertStringContainsString('form-capture-x155', $html, 'the listener deploy is missing the form marker');
        $this->assertStringContainsString('dni-pool-x137', $html, 'the listener deploy is missing the DNI marker');
        $this->assertStringContainsString('seo-meta-x176', $html, 'the listener deploy is missing the SEO title');
        $this->assertStringContainsString('rel="canonical"', $html, 'the listener deploy is missing the canonical link');
        $this->assertStringContainsString('application/ld+json', $html, 'the listener deploy is missing the schema block');

        $zone->update(['has_valid_ssl' => false]);
        $response = $this->get("/sites/{$biz->id}/{$deployment->deploy_hash}");
        $response->assertStatus(404);

        $zone->update(['has_valid_ssl' => true]);
        $response = $this->get("/sites/{$biz->id}/{$deployment->deploy_hash}");
        $response->assertStatus(200);
    }

    public function test_a_second_pages_publish_leaves_the_first_page_live(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");
        $zone = $this->provisionAction->handle($biz->id, 'acme-hvac.com', true);

        $home = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home']);
        $about = Page::create(['business_id' => $biz->id, 'title' => 'About', 'slug' => 'about']);

        app(SitePublishAction::class)->handle($biz->id, $home->id, [['type' => 'chat']]);

        $homeDeploy = Deployment::where('business_id', $biz->id)->where('status', 'deployed')->sole();
        $homeHash = $homeDeploy->deploy_hash;

        $this->assertSame(
            (int) $home->id,
            (int) $homeDeploy->page_id,
            'the deployment does not record which page it rendered'
        );

        app(SitePublishAction::class)->handle($biz->id, $about->id, [['type' => 'chat']]);

        $aboutDeploy = Deployment::where('business_id', $biz->id)->orderByDesc('id')->first();
        $this->assertNotSame($homeHash, $aboutDeploy->deploy_hash);

        $homeResponse = $this->get("/sites/{$biz->id}/{$homeHash}");
        $homeResponse->assertStatus(200);
        $this->assertStringContainsString(
            '<title id="seo-meta-x176">Home</title>',
            (string) $homeResponse->getContent(),
            'publishing a second page took the first page down'
        );

        $aboutResponse = $this->get("/sites/{$biz->id}/{$aboutDeploy->deploy_hash}");
        $aboutResponse->assertStatus(200);
        $this->assertStringContainsString(
            '<title id="seo-meta-x176">About</title>',
            (string) $aboutResponse->getContent(),
            'the second page did not serve its own artifact'
        );

        $this->assertSame(
            2,
            Deployment::where('business_id', $biz->id)->where('status', 'deployed')->count(),
            'two published pages must leave two live deployments'
        );
    }

    public function test_rolling_back_one_page_leaves_another_pages_history_alone(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");
        $zone = $this->provisionAction->handle($biz->id, 'acme-hvac.com', true);

        $home = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home']);
        $about = Page::create(['business_id' => $biz->id, 'title' => 'About', 'slug' => 'about']);

        app(SitePublishAction::class)->handle($biz->id, $home->id, [['type' => 'chat']]);
        $aRow = Deployment::where('business_id', $biz->id)->where('status', 'deployed')->sole();

        app(SitePublishAction::class)->handle($biz->id, $home->id, [['type' => 'chat']]);
        $bRow = Deployment::where('business_id', $biz->id)->where('status', 'deployed')->sole();

        app(SitePublishAction::class)->handle($biz->id, $about->id, [['type' => 'chat']]);
        $cRow = Deployment::where('business_id', $biz->id)
            ->where('page_id', $about->id)
            ->where('status', 'deployed')
            ->sole();

        $this->assertSame(
            'superseded',
            $aRow->refresh()->status,
            'the first home deploy should already be superseded before any rollback'
        );

        app(EdgeRollbackAction::class)->handle($biz->id, $cRow->id);

        $this->assertSame(
            'superseded',
            $aRow->refresh()->status,
            'rolling back the about page promoted a retired home deployment'
        );
        $this->get("/sites/{$biz->id}/{$aRow->deploy_hash}")->assertStatus(404);
        $this->assertSame(
            'deployed',
            $bRow->refresh()->status,
            'rolling back the about page disturbed the live home deploy'
        );
        $this->assertSame(
            1,
            Deployment::where('business_id', $biz->id)
                ->where('page_id', $home->id)
                ->where('status', 'deployed')
                ->count(),
            'the home page must have exactly one live deployment'
        );

        app(EdgeRollbackAction::class)->handle($biz->id, $bRow->id);

        $this->assertSame(
            'deployed',
            $aRow->refresh()->status,
            'rolling back the live home deploy did not restore its own predecessor'
        );
        $this->get("/sites/{$biz->id}/{$aRow->deploy_hash}")->assertStatus(200);
    }

    /** (R245) */
    public function test_the_published_form_posts_to_an_address_that_captures(): void
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

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Contact',
            'slug' => 'contact',
            'steps' => [],
            'schema' => [],
        ]);

        $deploy = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );

        $pageResp = $this->get("/sites/{$biz->id}/{$deploy['deploy_hash']}");
        $pageResp->assertStatus(200);

        $html = (string) $pageResp->getContent();
        $this->assertMatchesRegularExpression(
            '/action="[^"]+\/forms\/\\d+"/',
            $html,
            'the published form names no address'
        );

        preg_match('/action="[^"]*?(\/sites\/[^"]*?\/forms\/\\d+)"/', $html, $m);
        $this->assertSame(
            "/sites/{$biz->id}/{$deploy['deploy_hash']}/forms/{$form->id}",
            $m[1],
            'the published form posts to the wrong address'
        );

        $post = $this->post($m[1], [
            'first_name' => 'Rae',
            'phone' => '+15559990001',
            'email' => 'rae@example.com',
        ]);
        $post->assertStatus(201);

        $person = Person::where('business_id', $biz->id)->where('phone', '+15559990001')->first();
        $this->assertNotNull($person, 'the published form captured no contact');

        $this->assertSame('Rae', $person->first_name);

        $this->assertSame(1, FormSubmission::where('business_id', $biz->id)
            ->where('form_definition_id', $form->id)->count());

        $deployment = Deployment::where('deploy_hash', $deploy['deploy_hash'])->firstOrFail();
        app(EdgeRollbackAction::class)->handle($biz->id, $deployment->id);

        $this->post($m[1], [
            'first_name' => 'Sam',
            'phone' => '+15559990002',
        ])->assertStatus(404);

        $this->assertSame(1, FormSubmission::where('business_id', $biz->id)
            ->where('form_definition_id', $form->id)->count(),
            'a rolled back site still accepted a submission');
    }

    public function test_a_bot_filling_the_honeypot_is_refused_by_the_published_form_endpoint(): void
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

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Contact',
            'slug' => 'contact',
            'steps' => [],
            'schema' => [],
        ]);

        $deploy = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );

        $pageResp = $this->get("/sites/{$biz->id}/{$deploy['deploy_hash']}");
        $pageResp->assertStatus(200);

        $html = (string) $pageResp->getContent();
        preg_match('/action="[^"]*?(\/sites\/[^"]*?\/forms\/\\d+)"/', $html, $m);

        $post = $this->postJson($m[1], [
            'first_name' => 'Rae',
            'website_url' => 'http://spam-link.ru',
        ]);

        $post->assertStatus(422);
        $post->assertJson(['status' => 'rejected']);

        $submission = FormSubmission::where('business_id', $biz->id)
            ->where('form_definition_id', $form->id)
            ->firstOrFail();

        $this->assertTrue($submission->is_spam);
        $this->assertSame('honeypot_triggered', $submission->spam_reason);
        $this->assertSame('http://spam-link.ru', $submission->payload['website_url']);
    }

    public function test_the_published_form_refuses_a_form_belonging_to_another_business(): void
    {
        Storage::fake('local');
        $bizA = TestCase::provisionTenant(['name' => 'Tenant A', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$bizA->id}'");

        $page = Page::create([
            'business_id' => $bizA->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $site = app(SitePublishAction::class)
            ->handle($bizA->id, $page->id, [
                ['type' => 'chat'],
                ['type' => 'form_capture'],
                ['type' => 'dni'],
            ]);

        $zone = $this->provisionAction->handle($bizA->id, 'acme-a.com', true);

        $formA = FormDefinition::create([
            'business_id' => $bizA->id,
            'form_name' => 'Contact A',
            'slug' => 'contact-a',
            'steps' => [],
            'schema' => [],
        ]);

        $deploy = $this->deployAction->handle(
            businessId: $bizA->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $bizA->name
        );

        $bizB = TestCase::provisionTenant(['name' => 'Tenant B', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$bizB->id}'");

        $formB = FormDefinition::create([
            'business_id' => $bizB->id,
            'form_name' => 'Contact B',
            'slug' => 'contact-b',
            'steps' => [],
            'schema' => [],
        ]);

        $pageResp = $this->get("/sites/{$bizA->id}/{$deploy['deploy_hash']}");
        $pageResp->assertStatus(200);

        $html = (string) $pageResp->getContent();
        preg_match('/action="[^"]*?(\/sites\/[^"]*?\/forms\/\\d+)"/', $html, $m);

        $action = preg_replace('/\/forms\/\d+$/', '/forms/'.$formB->id, $m[1]);

        $post = $this->post($action, [
            'first_name' => 'Rae',
            'email' => 'rae@example.com',
        ]);

        $post->assertStatus(404);

        $count = FormSubmission::whereIn('business_id', [$bizA->id, $bizB->id])->count();
        $this->assertSame(0, $count, 'a cross-tenant form id captured a submission');
    }

    public function test_the_published_form_accepts_an_array_valued_field_without_a_server_error(): void
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

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Contact',
            'slug' => 'contact',
            'steps' => [],
            'schema' => [],
        ]);

        $deploy = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );

        $pageResp = $this->get("/sites/{$biz->id}/{$deploy['deploy_hash']}");
        $pageResp->assertStatus(200);

        $html = (string) $pageResp->getContent();
        preg_match('/action="[^"]*?(\/sites\/[^"]*?\/forms\/\\d+)"/', $html, $m);

        $post = $this->post($m[1], [
            'first_name' => 'Rae',
            'phone' => ['+15559990001'],
        ]);
        $post->assertStatus(201);

        $submission = FormSubmission::where('business_id', $biz->id)
            ->where('form_definition_id', $form->id)
            ->firstOrFail();

        $this->assertSame(['+15559990001'], $submission->payload['phone']);

        $person = Person::findOrFail($submission->person_id);
        $this->assertNull($person->phone);
        $this->assertSame('Rae', $person->first_name);
    }

    public function test_a_lapsed_certificate_stops_the_published_form_from_accepting_a_submission(): void
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

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Contact',
            'slug' => 'contact',
            'steps' => [],
            'schema' => [],
        ]);

        $deploy = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );

        $pageResp = $this->get("/sites/{$biz->id}/{$deploy['deploy_hash']}");
        $pageResp->assertStatus(200);

        $html = (string) $pageResp->getContent();
        preg_match('/action="[^"]*?(\/sites\/[^"]*?\/forms\/\\d+)"/', $html, $m);

        $post = $this->post($m[1], [
            'first_name' => 'Rae',
            'phone' => '+15559990001',
            'email' => 'rae@example.com',
        ]);
        $post->assertStatus(201);

        $this->assertSame(1, FormSubmission::where('business_id', $biz->id)
            ->where('form_definition_id', $form->id)->count());

        $zone->update(['has_valid_ssl' => false]);

        $this->get("/sites/{$biz->id}/{$deploy['deploy_hash']}")->assertStatus(404);

        $this->post($m[1], [
            'first_name' => 'Rae',
            'phone' => '+15559990001',
            'email' => 'rae@example.com',
        ])->assertStatus(404);

        $this->assertSame(1, FormSubmission::where('business_id', $biz->id)
            ->where('form_definition_id', $form->id)->count(),
            'a site whose certificate lapsed still accepted a submission');
    }

    /** (R245) */
    public function test_a_video_block_becomes_videoobject_in_the_served_page_schema(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Video Tenant', 'currency' => 'USD']);
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
                [
                    'type' => 'video_embed',
                    'name' => 'Drain Clearing Explained',
                    'contentUrl' => 'https://video.example.com/drain.mp4',
                    'uploadDate' => '2026-09-01',
                ],
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
        $body = (string) $response->getContent();

        $this->assertSame(
            1,
            preg_match('#<script type="application/ld\+json">\s*(.*?)\s*</script>#s', $body, $m),
            'the served page carries no JSON-LD block at all'
        );
        $ld = json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('VideoObject', $ld['video'][0]['@type'] ?? null);
        $this->assertSame('Drain Clearing Explained', $ld['video'][0]['name'] ?? null);
        $this->assertSame('https://video.example.com/drain.mp4', $ld['video'][0]['contentUrl'] ?? null);
        $this->assertSame('2026-09-01', $ld['video'][0]['uploadDate'] ?? null);
    }

    public function test_a_malformed_video_block_is_omitted_from_the_served_page_schema(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Video Tenant', 'currency' => 'USD']);
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
                [
                    'type' => 'video_embed',
                    'name' => 'Broken',
                    'uploadDate' => '2026-09-01',
                ],
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
        $body = (string) $response->getContent();

        $this->assertStringContainsString('application/ld+json', $body);
        $this->assertStringNotContainsString('Broken', $body);
        $this->assertStringContainsString('dni-pool-x137', $body);
    }

    public function test_the_published_form_refuses_a_minor_and_writes_no_contact(): void
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

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Contact',
            'slug' => 'contact',
            'steps' => [],
            'schema' => [],
        ]);

        $deploy = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );

        $pageResp = $this->get("/sites/{$biz->id}/{$deploy['deploy_hash']}");
        $pageResp->assertStatus(200);

        $html = (string) $pageResp->getContent();
        preg_match('/action="[^"]*?(\/sites\/[^"]*?\/forms\/\\d+)"/', $html, $m);

        $post = $this->postJson($m[1], [
            'first_name' => 'Kid',
            'phone' => '+15550008181',
            'date_of_birth' => now()->subYears(15)->toDateString(),
        ]);

        $this->assertSame(0, Person::where('business_id', $biz->id)->where('phone', '+15550008181')->count(),
            'P-148: an under-18 signal at ingest wrote a contact row through the published form');

        $this->assertSame(0, FormSubmission::where('business_id', $biz->id)->count(),
            'P-148: an under-18 submission was stored through the published form');

        $post->assertStatus(422);
        $this->assertSame('under_18', $post->json('reason'));
    }

    public function test_the_published_form_refuses_an_unanswered_required_step(): void
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

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Contact',
            'slug' => 'contact',
            'steps' => [
                ['step' => 1, 'required' => ['project_type']],
            ],
            'schema' => [],
        ]);

        $deploy = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );

        $pageResp = $this->get("/sites/{$biz->id}/{$deploy['deploy_hash']}");
        $pageResp->assertStatus(200);

        $html = (string) $pageResp->getContent();
        preg_match('/action="[^"]*?(\/sites\/[^"]*?\/forms\/\\d+)"/', $html, $m);

        $postA = $this->postJson($m[1], [
            'first_name' => 'John',
            'phone' => '+15550008182',
            'project_type' => 'roofing',
        ]);

        $postA->assertStatus(201);
        $this->assertSame('captured', $postA->json('status'));
        $this->assertSame(1, FormSubmission::where('business_id', $biz->id)->where('form_definition_id', $form->id)->count());

        $postB = $this->postJson($m[1], [
            'first_name' => 'John',
            'phone' => '+15550008182',
        ]);

        $postB->assertStatus(422);
        $this->assertSame('incomplete_step', $postB->json('reason'));
        $this->assertContains('project_type', $postB->json('missing'));
        $this->assertSame(1, $postB->json('step'));
        $this->assertSame(1, FormSubmission::where('business_id', $biz->id)->where('form_definition_id', $form->id)->count());
    }

    public function test_the_published_form_skips_a_malformed_required_field_and_still_enforces_its_siblings(): void
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

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Contact',
            'slug' => 'contact',
            'steps' => [
                ['step' => 1, 'required' => [['nested'], 'project_type']],
            ],
            'schema' => [],
        ]);

        $deploy = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );

        $pageResp = $this->get("/sites/{$biz->id}/{$deploy['deploy_hash']}");
        $pageResp->assertStatus(200);

        $html = (string) $pageResp->getContent();
        preg_match('/action="[^"]*?(\/sites\/[^"]*?\/forms\/\\d+)"/', $html, $m);

        $postB = $this->postJson($m[1], [
            'first_name' => 'John',
            'phone' => '+15550008182',
        ]);

        $postB->assertStatus(422);
        $this->assertSame(['project_type'], $postB->json('missing'));
        $this->assertSame('incomplete_step', $postB->json('reason'));

        $postA = $this->postJson($m[1], [
            'first_name' => 'John',
            'phone' => '+15550008182',
            'project_type' => 'roofing',
        ]);

        $postA->assertStatus(201);
        $this->assertSame('captured', $postA->json('status'));
        $this->assertSame(1, FormSubmission::where('business_id', $biz->id)->where('form_definition_id', $form->id)->count());
    }

    public function test_tenant_media_route_serves_image_and_enforces_auth_and_existence(): void
    {
        Http::fake();
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $zone = $this->provisionAction->handle($biz->id, 'acme.com', true);

        $deploy = Deployment::create([
            'business_id' => $biz->id,
            'edge_zone_id' => $zone->id,
            'deploy_hash' => 'test_hash_media',
            'status' => 'deployed',
            'measured_ttfb_ms' => 100,
            'speed_budget_ms' => 1500,
        ]);

        Storage::disk('local')->put("site-inventory/{$biz->id}/image.jpg", base64_decode('R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw=='));

        $response = $this->get("/sites/{$biz->id}/test_hash_media/media/image.jpg");
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/gif');

        $this->get("/sites/{$biz->id}/test_hash_media/media/missing.jpg")->assertStatus(404);

        $biz2 = TestCase::provisionTenant(['name' => 'Foreign', 'currency' => 'USD']);
        $this->get("/sites/{$biz2->id}/test_hash_media/media/image.jpg")->assertStatus(404);
    }

    public function test_deploy_emits_title_and_description_escaped(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Deploy Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $zone = EdgeZone::create([
            'business_id' => $biz->id,
            'domain_name' => 'fox.test',
            'zone_id' => 'z1',
            'has_valid_ssl' => true,
        ]);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Fox < Title',
            'seo_title' => 'Fox & Sons Drains',
            'seo_description' => 'Fast & reliable "drain" cleaning.',
            'is_published' => false,
        ]);

        $action = app(EdgeDeployAction::class);
        $res = $action->handle($biz->id, $zone->id, 120, 1500, $page->id, 'commit_123', 'Fox Business');

        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        $this->assertStringContainsString('<title>Fox &amp; Sons Drains</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Fast &amp; reliable &quot;drain&quot; cleaning.">', $html);

        $page2 = Page::create([
            'business_id' => $biz->id,
            'slug' => 'about',
            'title' => 'Fox & Page',
            'is_published' => false,
        ]);

        $res2 = $action->handle($biz->id, $zone->id, 120, 1500, $page2->id, 'commit_456', 'Fox Business');
        $html2 = Storage::disk('local')->get("sites/{$res2['deploy_hash']}.html");

        $this->assertStringContainsString('<title>Fox &amp; Page</title>', $html2);
    }

    public function test_a_page_with_the_seo_block_and_an_owner_title_deploys_exactly_one_title(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");
        $zone = $this->provisionAction->handle($biz->id, 'acme-hvac.com', true);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Fox < Title',
            'seo_title' => 'Fox & Sons Drains',
            'seo_description' => 'Fast & reliable "drain" cleaning.',
            'is_published' => true,
        ]);

        $action = app(EdgeDeployAction::class);
        $res = $action->handle($biz->id, $zone->id, 120, 1500, $page->id, 'commit_123', 'Fox Business');

        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        $this->assertSame(
            1,
            substr_count($html, '<title'),
            'exactly one title tag expected'
        );
        $this->assertStringContainsString('<title id="seo-meta-x176">Fox &amp; Sons Drains</title>', $html);
        $this->assertSame(1, substr_count($html, 'name="description"'));
    }

    public function test_sitemap_lists_only_deployed_pages_newest_first(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");
        $zone = EdgeZone::create(['business_id' => $biz->id, 'domain_name' => 'acme.com', 'zone_id' => 'z', 'has_valid_ssl' => true]);

        $d1 = Deployment::create(['business_id' => $biz->id, 'edge_zone_id' => $zone->id, 'deploy_hash' => 'h1', 'status' => 'deployed', 'page_id' => 10, 'deployed_at' => now()->subDay()]);
        $d2 = Deployment::create(['business_id' => $biz->id, 'edge_zone_id' => $zone->id, 'deploy_hash' => 'h2', 'status' => 'rolled_back', 'page_id' => 10, 'deployed_at' => now()->subHours(12)]);
        $d3 = Deployment::create(['business_id' => $biz->id, 'edge_zone_id' => $zone->id, 'deploy_hash' => 'fox-&-hound', 'status' => 'deployed', 'page_id' => 11, 'deployed_at' => now()]);

        $xml = app(SitemapRenderAction::class)->handle($biz->id);

        $this->assertSame(2, substr_count($xml, '<loc>'));
        $this->assertStringContainsString('fox-&amp;-hound', $xml);
        $this->assertStringContainsString($d3->deployed_at->toW3cString(), $xml);

        $pos3 = strpos($xml, 'fox-&amp;-hound');
        $pos1 = strpos($xml, 'h1');
        $this->assertTrue($pos3 < $pos1);
    }

    public function test_at_the_platform_address_the_sitemap_lists_stable_page_urls(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");
        $zone = app(PlatformSiteAddressAction::class)->handle($biz->id);

        $p1 = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home', 'is_published' => true]);
        $p2 = Page::create(['business_id' => $biz->id, 'title' => 'Dist', 'slug' => 'distinctive-4935', 'is_published' => true]);

        Deployment::create(['business_id' => $biz->id, 'edge_zone_id' => $zone->id, 'deploy_hash' => 'hA4935', 'status' => 'deployed', 'page_id' => $p1->id, 'deployed_at' => now()->subDay()]);
        Deployment::create(['business_id' => $biz->id, 'edge_zone_id' => $zone->id, 'deploy_hash' => 'hB4935', 'status' => 'deployed', 'page_id' => $p2->id, 'deployed_at' => now()]);

        $xml = app(SitemapRenderAction::class)->handle($biz->id);

        $this->assertSame(2, substr_count($xml, '<loc>'));
        $this->assertStringContainsString("/sites/{$biz->id}/p/home", $xml);
        $this->assertStringContainsString("/sites/{$biz->id}/p/distinctive-4935", $xml);
        $this->assertStringNotContainsString('hA4935', $xml);
        $this->assertStringNotContainsString('hB4935', $xml);
    }

    public function test_sitemap_and_robots_404_for_an_unknown_hash(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        $this->get("/sites/{$biz->id}/unknown_hash/sitemap.xml")->assertStatus(404);
        $this->get("/sites/{$biz->id}/unknown_hash/robots.txt")->assertStatus(404);
    }

    public function test_robots_disallows_dni_and_forms_and_names_the_sitemap(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        $zone = EdgeZone::create(['business_id' => $biz->id, 'domain_name' => 'acme.com', 'zone_id' => 'z', 'has_valid_ssl' => true]);
        Deployment::create(['business_id' => $biz->id, 'edge_zone_id' => $zone->id, 'deploy_hash' => 'h1', 'status' => 'deployed']);

        $txt = $this->get("/sites/{$biz->id}/h1/robots.txt")->getContent();
        $this->assertStringContainsString("Disallow: /sites/{$biz->id}/h1/dni", $txt);
        $this->assertStringContainsString("Disallow: /sites/{$biz->id}/h1/forms/", $txt);
        $this->assertStringContainsString('Sitemap: '.route('x-157.site', ['business' => $biz->id, 'deploy_hash' => 'h1']).'/sitemap.xml', $txt);
    }

    public function test_another_tenants_pages_never_appear(): void
    {
        $ownerA = User::factory()->create();
        $bizA = TestCase::provisionTenant(['name' => 'Biz A', 'currency' => 'USD', 'owner_user_id' => $ownerA->id]);
        $zoneA = EdgeZone::create(['business_id' => $bizA->id, 'domain_name' => 'a.com', 'zone_id' => 'z_a', 'has_valid_ssl' => true]);
        Deployment::create(['business_id' => $bizA->id, 'edge_zone_id' => $zoneA->id, 'deploy_hash' => 'hash-a', 'status' => 'deployed', 'page_id' => 1, 'deployed_at' => now()]);

        $ownerB = User::factory()->create();
        $bizB = TestCase::provisionTenant(['name' => 'Biz B', 'currency' => 'USD', 'owner_user_id' => $ownerB->id]);
        Tenancy::setUser($ownerB->id);
        Tenancy::set((int) $bizB->id);

        $zoneB = EdgeZone::create(['business_id' => $bizB->id, 'domain_name' => 'b.com', 'zone_id' => 'z_b', 'has_valid_ssl' => true]);
        Deployment::create(['business_id' => $bizB->id, 'edge_zone_id' => $zoneB->id, 'deploy_hash' => 'hash-b', 'status' => 'deployed', 'page_id' => 2, 'deployed_at' => now()]);

        $xml = app(SitemapRenderAction::class)->handle($bizB->id);
        $this->assertStringContainsString('hash-b', $xml);
        $this->assertStringNotContainsString('hash-a', $xml);
    }

    public function test_a_drafted_form_submission_reaches_form_capture(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant Form', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Lead Form',
            'form_name' => 'Lead Form',
            'slug' => 'lead',
            'steps' => [['required' => ['name', 'phone']]],
            'schema' => [],
            'honeypot_field' => 'website_url',
        ]);

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $site = app(SitePublishAction::class)
            ->handle($biz->id, $page->id, [
                [
                    'type' => 'form',
                    'definition_id' => $form->id,
                    'fields' => [
                        ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
                        ['name' => 'phone', 'label' => 'Phone', 'type' => 'tel'],
                        ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
                        ['name' => 'message', 'label' => 'Message', 'type' => 'textarea'],
                    ],
                    'required' => ['name', 'phone'],
                    'honeypot' => 'website_url',
                ],
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

        $actionUrl = "/sites/{$biz->id}/{$deploy['deploy_hash']}/forms/{$form->id}";

        $res1 = $this->post($actionUrl, [
            'name' => 'Alice',
            'phone' => '1234567890',
            'email' => 'alice@example.com',
            'message' => 'Hello',
        ]);
        $res1->assertStatus(201);

        $submission1 = FormSubmission::where('form_definition_id', $form->id)->first();
        $this->assertFalse($submission1->is_spam);

        $res2 = $this->post($actionUrl, [
            'name' => 'Bob',
            'phone' => '0987654321',
            'email' => 'bob@example.com',
            'message' => 'Spam',
            'website_url' => 'http://spam.com',
        ]);
        $res2->assertStatus(422);

        $submission2 = FormSubmission::where('form_definition_id', $form->id)->orderBy('id', 'desc')->first();
        $this->assertTrue($submission2->is_spam);
        $this->assertEquals('honeypot_triggered', $submission2->spam_reason);
    }

    public function test_a_domain_whose_cname_points_at_the_platform_address_is_verified(): void
    {
        $biz = TestCase::provisionTenant();
        CustomDomainRequest::create([
            'business_id' => $biz->id,
            'domain' => 'acme-roofing.test',
            'status' => 'requested',
        ]);

        $action = app(CustomDomainVerifyAction::class);
        $action->handle($biz->id);

        $row = CustomDomainRequest::where('business_id', $biz->id)->first();
        $this->assertSame('verified', $row->status);
        $this->assertNotNull($row->verified_at);
        $this->assertNotNull($row->last_checked_at);
        $this->assertNull($row->failure_reason);
    }

    public function test_a_domain_with_no_cname_is_unverified_with_the_reason(): void
    {
        $biz = TestCase::provisionTenant();
        CustomDomainRequest::create([
            'business_id' => $biz->id,
            'domain' => 'missing.test',
            'status' => 'requested',
        ]);

        $action = app(CustomDomainVerifyAction::class);
        $action->handle($biz->id);

        $row = CustomDomainRequest::where('business_id', $biz->id)->first();
        $this->assertSame('unverified', $row->status);
        $this->assertSame('no_cname', $row->failure_reason);
    }

    public function test_a_domain_pointing_elsewhere_names_where_it_points(): void
    {
        $biz = TestCase::provisionTenant();
        CustomDomainRequest::create([
            'business_id' => $biz->id,
            'domain' => 'other.test',
            'status' => 'requested',
        ]);

        $action = app(CustomDomainVerifyAction::class);
        $action->handle($biz->id);

        $row = CustomDomainRequest::where('business_id', $biz->id)->first();
        $this->assertSame('unverified', $row->status);
        $this->assertSame('points_elsewhere:other.com', $row->failure_reason);
    }

    public function test_the_served_site_answers_on_a_verified_custom_host(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $zone = $this->provisionAction->handle($biz->id, 'acme-roofing.test', true);

        DB::table('custom_domain_requests')->where('domain', 'acme-roofing.test')->delete();
        // Use pgsql_migrate so the middleware can see it without transaction isolation issues
        DB::table('custom_domain_requests')->insert([
            'business_id' => $biz->id,
            'domain' => 'acme-roofing.test',
            'status' => 'verified',
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'My Verified Site',
            'slug' => 'home',
            'is_published' => true,
        ]);

        $commit = app(SitePublishAction::class)->handle($biz->id, $page->id, []);

        $deploy = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $commit['commit_id'],
            businessName: $biz->name
        );

        $res2 = $this->get('http://acme-roofing.test/');
        $res2->assertSee('<title id="seo-meta-x176">My Verified Site</title>', false);
        DB::table('custom_domain_requests')->where('domain', 'acme-roofing.test')->delete();
    }

    public function test_llms_txt_is_served_on_the_platform_route_and_on_a_verified_custom_domain(): void
    {
        Storage::fake('local');
        Route::fallback(function () {
            return abort(404);
        })->middleware('web');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $zone = $this->provisionAction->handle($biz->id, 'acme-llms.test', true);

        DB::table('custom_domain_requests')->where('domain', 'acme-llms.test')->delete();
        DB::table('custom_domain_requests')->insert([
            'business_id' => $biz->id,
            'domain' => 'acme-llms.test',
            'status' => 'verified',
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'My Verified Site',
            'slug' => 'home',
            'is_published' => true,
        ]);

        $commit = app(SitePublishAction::class)->handle($biz->id, $page->id, []);

        $deploy = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $commit['commit_id'],
            businessName: $biz->name
        );

        $res1 = $this->get("/sites/{$biz->id}/{$deploy['deploy_hash']}/llms.txt");
        $res1->assertOk();
        $this->assertStringStartsWith('text/plain', $res1->headers->get('Content-Type'));
        $this->assertStringStartsWith('# '.$biz->name, $res1->getContent());

        $res2 = $this->get('http://acme-llms.test/llms.txt');
        $res2->assertOk();
        $this->assertStringStartsWith('text/plain', $res2->headers->get('Content-Type'));
        $this->assertStringStartsWith('# '.$biz->name, $res2->getContent());

        $res3 = $this->get("/sites/{$biz->id}/nonexistent/llms.txt");
        $res3->assertStatus(404);

        DB::table('custom_domain_requests')->where('domain', 'acme-llms.test')->delete();
    }

    public function test_the_app_host_is_never_treated_as_a_custom_domain(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        $appHost = parse_url(config('app.url'), PHP_URL_HOST);

        $res = $this->get("http://{$appHost}/login");
        $res->assertStatus(200); // the normal app routing
    }

    public function test_a_deployed_form_renders_the_tenants_form_fields_and_posts_to_the_capture_route(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        app(FormCreateAction::class)->handle((int) $biz->id, 'Contact Form');

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
                ['type' => 'form_capture'],
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

        $this->assertSame(1, substr_count($html, 'form-capture-x155'));
        $this->assertStringContainsString('name="phone"', $html);
        $this->assertStringContainsString('<textarea name="message"', $html);
        $this->assertStringContainsString("/sites/{$biz->id}/{$deploy['deploy_hash']}/forms/", $html);

        preg_match('/action="([^"]+\/forms\/\d+)"/', $html, $m);
        $res = $this->post($m[1], ['name' => 'Distinctive Visitor 4471', 'phone' => '+15125567731', 'message' => 'hello']);
        if ($res->status() === 419) {
            $this->fail('419 CSRF error');
        }
        $res->assertStatus(201);

        Tenancy::set((int) $biz->id);
        $this->assertDatabaseHas('form_submissions', ['business_id' => $biz->id]);
    }

    public function test_a_deployed_page_with_no_form_definition_keeps_the_marker_and_no_inputs(): void
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
                ['type' => 'form_capture'],
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

        $this->assertSame(1, substr_count($html, 'form-capture-x155'));
        $this->assertStringNotContainsString('name="phone"', $html);
    }

    public function test_the_deployed_pixel_tag_carries_the_tenants_key(): void
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
                ['type' => 'pixel_script'],
                ['type' => 'chat_widget'],
                ['type' => 'form_capture'],
                ['type' => 'dni_script'],
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

        preg_match('/<script\s+id="x110-pixel"\s+src="([^"]+)"\s+data-k="([^"]+)"/', $html, $m);
        $this->assertSame(app(PixelKeys::class)->forBusiness(Business::findOrFail($biz->id)), $m[2]);
        $this->assertSame(1, substr_count($html, 'x110-pixel'));
    }

    public function test_the_deployed_dni_container_carries_its_endpoint_and_one_swap_script(): void
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
                ['type' => 'pixel_script'],
                ['type' => 'chat_widget'],
                ['type' => 'form_capture'],
                ['type' => 'dni_script'],
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

        $this->assertSame(1, substr_count($html, 'dni-pool-x137'));
        $this->assertStringContainsString('data-dni-url="/sites/'.$biz->id.'/'.$deploy['deploy_hash'].'/dni"', $html);
        $this->assertSame(1, substr_count($html, 'id="x137-dni"'));
        $this->assertSame(substr_count($html, '<script'), substr_count($html, '</script>'));

        $response = $this->get("/sites/{$biz->id}/{$deploy['deploy_hash']}/dni?visitor_session_token=dni_distinctive4471");
        // We will output this response code in the FINDINGS.
        $response->assertStatus(409);
    }

    public function test_the_deployed_chat_widget_is_a_real_script_keyed_by_the_pixel_key(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Chat Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $zone = $this->provisionAction->handle($biz->id, 'chat.example.com', true);

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $commitId = Str::random(12);
        PageVersion::create([
            'business_id' => $biz->id,
            'page_id' => $page->id,
            'commit_id' => $commitId,
            'content_blocks' => [
                ['type' => 'chat_widget'],
            ],
            'pixel_installed' => true,
        ]);

        $deploy = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $commitId,
            businessName: $biz->name
        );

        $html = Storage::disk('local')->get("sites/{$deploy['deploy_hash']}.html");

        $this->assertSame(1, substr_count($html, 'chat-widget-container'));
        $this->assertSame(1, preg_match('/<script\s+id="x102-chat"\s+src="([^"]+)"\s+data-chat\s+data-key="([^"]+)"/', $html, $m));

        $this->get($m[1])->assertOk();

        Tenancy::set($biz->id);
        $this->assertSame(app(PixelKeys::class)->forBusiness($biz), $m[2]);
        $this->assertSame(substr_count($html, '<script'), substr_count($html, '</script>'));

        Tenancy::forgetAll();
        $this->postJson("/api/chat/{$m[2]}/start")->assertStatus(201)->assertJsonStructure(['session_token']);
    }

    public function test_a_custom_domain_serves_each_published_page_by_its_slug(): void
    {
        Storage::fake('local');
        Route::fallback(function () {
            return abort(404);
        })->middleware('web');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");
        DB::table('pages')->where('business_id', $biz->id)->delete();

        $zone = $this->provisionAction->handle($biz->id, 'acme-roofing.test', true);

        DB::table('custom_domain_requests')->where('domain', 'acme-roofing.test')->delete();
        DB::table('custom_domain_requests')->insert([
            'business_id' => $biz->id,
            'domain' => 'acme-roofing.test',
            'status' => 'verified',
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $page1 = Page::create([
            'business_id' => $biz->id,
            'title' => 'Distinctive home 4471',
            'slug' => 'home',
            'is_published' => true,
        ]);
        $commit1 = app(SitePublishAction::class)->handle($biz->id, $page1->id, []);
        $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page1->id,
            commitId: $commit1['commit_id'],
            businessName: $biz->name
        );

        $page2 = Page::create([
            'business_id' => $biz->id,
            'title' => 'Distinctive services 4472',
            'slug' => 'services',
            'is_published' => true,
        ]);
        $commit2 = app(SitePublishAction::class)->handle($biz->id, $page2->id, []);
        $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page2->id,
            commitId: $commit2['commit_id'],
            businessName: $biz->name
        );

        $this->get('http://acme-roofing.test/')->assertOk()->assertSee('Distinctive home 4471')->assertDontSee('Distinctive services 4472');
        $this->get('http://acme-roofing.test/services')->assertOk()->assertSee('Distinctive services 4472');
        $this->get('http://acme-roofing.test/services/')->assertOk()->assertSee('Distinctive services 4472');
        $this->get('http://acme-roofing.test/nope')->assertNotFound();
        $this->get('http://acme-roofing.test/sitemap.xml')->assertOk()->assertSee('https://acme-roofing.test/services', false)->assertDontSee('/sites/', false);

        DB::table('custom_domain_requests')->where('domain', 'acme-roofing.test')->delete();
    }

    public function test_a_custom_domain_with_no_home_page_still_answers_at_the_root(): void
    {
        Storage::fake('local');
        Route::fallback(function () {
            return abort(404);
        })->middleware('web');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");
        DB::table('pages')->where('business_id', $biz->id)->delete();

        $zone = $this->provisionAction->handle($biz->id, 'acme-roofing.test', true);

        DB::table('custom_domain_requests')->where('domain', 'acme-roofing.test')->delete();
        DB::table('custom_domain_requests')->insert([
            'business_id' => $biz->id,
            'domain' => 'acme-roofing.test',
            'status' => 'verified',
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Distinctive about 4473',
            'slug' => 'about',
            'is_published' => true,
        ]);
        $commit = app(SitePublishAction::class)->handle($biz->id, $page->id, []);
        $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $commit['commit_id'],
            businessName: $biz->name
        );

        $this->get('http://acme-roofing.test/')->assertOk()->assertSee('Distinctive about 4473');
        $this->get('http://acme-roofing.test/about')->assertOk()->assertSee('Distinctive about 4473');

        DB::table('custom_domain_requests')->where('domain', 'acme-roofing.test')->delete();
    }

    public function test_the_sitemap_and_rollback_see_only_the_control_arm(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $zone = $this->provisionAction->handle($biz->id, 'acme-roofing.test', true);

        DB::table('custom_domain_requests')->where('domain', 'acme-roofing.test')->delete();
        DB::table('custom_domain_requests')->insert([
            'business_id' => $biz->id,
            'domain' => 'acme-roofing.test',
            'status' => 'verified',
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'My Verified Site',
            'slug' => 'home',
            'is_published' => true,
        ]);

        $commit = app(SitePublishAction::class)->handle($biz->id, $page->id, []);

        $controlDeploy = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $commit['commit_id'],
            businessName: $biz->name
        );

        $variantDeploy = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $commit['commit_id'],
            businessName: $biz->name,
            pageVariantId: 4563
        );

        $xml = app(SitemapRenderAction::class)->handle($biz->id);
        $this->assertStringContainsString($controlDeploy['deploy_hash'], $xml);
        $this->assertStringNotContainsString($variantDeploy['deploy_hash'], $xml);

        $this->expectException(HttpException::class);
        app(EdgeRollbackAction::class)->handle($biz->id, $variantDeploy['deployment_id']);
    }

    public function test_on_a_verified_custom_domain_the_deployed_forms_post_to_the_same_host_and_the_post_reaches_the_capture_route(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $zone = $this->provisionAction->handle($biz->id, 'acme-roofing.test', true);

        DB::table('custom_domain_requests')->where('domain', 'acme-roofing.test')->delete();
        DB::table('custom_domain_requests')->insert([
            'business_id' => $biz->id,
            'domain' => 'acme-roofing.test',
            'status' => 'verified',
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Lead Form',
            'slug' => 'lead',
            'steps' => [['required' => ['name']]],
            'schema' => [],
            'honeypot_field' => 'website_url',
        ]);

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $commit = app(SitePublishAction::class)->handle($biz->id, $page->id, [
            ['type' => 'form_capture'],
        ]);

        $deploy = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $commit['commit_id'],
            businessName: $biz->name
        );

        $html = Storage::disk('local')->get("sites/{$deploy['deploy_hash']}.html");
        $this->assertStringContainsString('action="/sites/', $html);
        $this->assertStringNotContainsString('action="http', $html);

        $res = $this->postJson('http://acme-roofing.test/sites/'.$biz->id.'/'.$deploy['deploy_hash'].'/forms/'.$form->id, [
            'name' => 'Distinctive lead 4621',
        ]);
        $res->assertStatus(201);
        $this->assertSame('captured', $res->json('status'));

        $res2 = $this->get('http://acme-roofing.test/');
        $res2->assertOk();

        DB::table('custom_domain_requests')->where('domain', 'acme-roofing.test')->delete();
    }

    public function test_a_verified_custom_domain_is_the_canonical_host_of_every_deployed_page(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $zone = $this->provisionAction->handle($biz->id, 'acme-roofing.test', true);

        DB::table('custom_domain_requests')->where('domain', 'acme-roofing.test')->delete();
        DB::table('custom_domain_requests')->insert([
            'business_id' => $biz->id,
            'domain' => 'acme-roofing.test',
            'status' => 'verified',
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Lead Form',
            'slug' => 'lead',
            'steps' => [['required' => ['name']]],
            'schema' => [],
            'honeypot_field' => 'website_url',
        ]);

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $commit = app(SitePublishAction::class)->handle($biz->id, $page->id, [
            ['type' => 'form_capture'],
        ]);

        $deploy = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $commit['commit_id'],
            businessName: $biz->name
        );

        $html = Storage::disk('local')->get("sites/{$deploy['deploy_hash']}.html");
        $this->assertStringContainsString('<link rel="canonical" href="https://acme-roofing.test/', $html);
        $this->assertStringContainsString('"url":"https:\/\/acme-roofing.test\/', $html);

        DB::table('custom_domain_requests')->where('domain', 'acme-roofing.test')->delete();

        $biz2 = TestCase::provisionTenant(['name' => 'Second Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz2->id}'");
        $zone2 = app(PlatformSiteAddressAction::class)->handle($biz2->id);

        $page2 = Page::create([
            'business_id' => $biz2->id,
            'title' => 'Home 2',
            'slug' => 'home',
        ]);

        $commit2 = app(SitePublishAction::class)->handle($biz2->id, $page2->id, [
            ['type' => 'form_capture'],
        ]);

        $deploy2 = $this->deployAction->handle(
            businessId: $biz2->id,
            edgeZoneId: $zone2->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page2->id,
            commitId: $commit2['commit_id'],
            businessName: $biz2->name
        );

        $appHost = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';
        $html2 = Storage::disk('local')->get("sites/{$deploy2['deploy_hash']}.html");
        $this->assertStringContainsString('<link rel="canonical" href="https://'.$appHost.'/', $html2);
    }

    public function test_the_published_form_comes_from_the_oldest_definition_with_fields(): void
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

        FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Fieldless Form',
            'slug' => 'fieldless-4903',
            'schema' => ['fields' => []],
            'steps' => [['step' => 1, 'required' => []]],
        ]);
        FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Real Form',
            'slug' => 'real-4904',
            'schema' => ['fields' => [['name' => 'phone', 'label' => 'Phone', 'type' => 'tel']]],
            'steps' => [['step' => 1, 'required' => ['phone']]],
        ]);

        $commitId = 'commit_'.Str::random(16);
        PageVersion::create([
            'business_id' => $biz->id,
            'page_id' => $page->id,
            'commit_id' => $commitId,
            'content_blocks' => [
                ['type' => 'pixel_script'],
                ['type' => 'chat_widget'],
                ['type' => 'form_capture'],
                ['type' => 'dni_script'],
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

        $this->assertStringContainsString('form-capture-x155', $html);
        $this->assertStringContainsString('name="phone"', $html);
    }

    public function test_a_browser_submitting_the_published_form_sees_a_thank_you_page_not_json(): void
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

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Contact',
            'slug' => 'contact',
            'steps' => [],
            'schema' => [],
        ]);

        $deploy = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );

        $pageResp = $this->get("/sites/{$biz->id}/{$deploy['deploy_hash']}");
        $html = (string) $pageResp->getContent();
        preg_match('/action="[^"]*?(\/sites\/[^"]*?\/forms\/\\d+)"/', $html, $m);

        $post = $this->withHeaders(['Accept' => 'text/html'])->post($m[1], ['first_name' => 'Distinctive visitor 4915', 'phone' => '+15559994915']);
        $post->assertStatus(201);
        $post->assertSee('Thanks — your message is in.');
        $post->assertDontSee('submission_id');
        $post->assertDontSee('person_id');

        $person = Person::where('business_id', $biz->id)->where('phone', '+15559994915')->first();
        $this->assertNotNull($person);
    }

    public function test_a_browser_giving_an_unreadable_date_of_birth_is_told_so(): void
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

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Contact',
            'slug' => 'contact',
            'steps' => [],
            'schema' => [],
        ]);

        $deploy = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $site['commit_id'],
            businessName: $biz->name
        );

        $pageResp = $this->get("/sites/{$biz->id}/{$deploy['deploy_hash']}");
        $html = (string) $pageResp->getContent();
        preg_match('/action="[^"]*?(\/sites\/[^"]*?\/forms\/\\d+)"/', $html, $m);

        $post = $this->withHeaders(['Accept' => 'text/html'])->post($m[1], ['first_name' => 'Distinctive visitor 4915', 'phone' => '+15559994915', 'date_of_birth' => 'Distinctive nonsense 4927']);
        $post->assertStatus(422);
        $post->assertSee('read the date of birth');
        $post->assertDontSee('dob_unreadable');
    }

    public function test_a_browser_missing_a_required_field_sees_the_field_named_not_a_json_blob(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant Form', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $form = FormDefinition::create([
            'business_id' => $biz->id,
            'form_name' => 'Lead Form',
            'slug' => 'lead',
            'steps' => [['required' => ['email']]],
            'schema' => [],
            'honeypot_field' => 'website_url',
        ]);

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $site = app(SitePublishAction::class)
            ->handle($biz->id, $page->id, [
                [
                    'type' => 'form',
                    'definition_id' => $form->id,
                    'fields' => [
                        ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
                    ],
                    'required' => ['email'],
                    'honeypot' => 'website_url',
                ],
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

        $actionUrl = "/sites/{$biz->id}/{$deploy['deploy_hash']}/forms/{$form->id}";

        $res1 = $this->withHeaders(['Accept' => 'text/html'])->post($actionUrl, []);
        $res1->assertStatus(422);
        $res1->assertSee('email');
        $res1->assertDontSee('incomplete_step');
    }

    public function test_at_the_platform_address_the_nav_and_canonical_use_the_stable_page_route(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Platform Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $zone = app(PlatformSiteAddressAction::class)->handle($biz->id);

        $home = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);
        $page2 = Page::create([
            'business_id' => $biz->id,
            'title' => 'Distinctive page 4931',
            'slug' => 'distinctive-4931',
        ]);

        $commitHome = app(SitePublishAction::class)->handle($biz->id, $home->id, []);
        $commit2 = app(SitePublishAction::class)->handle($biz->id, $page2->id, []);

        $deployHome = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $home->id,
            commitId: $commitHome['commit_id'],
            businessName: $biz->name
        );
        $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page2->id,
            commitId: $commit2['commit_id'],
            businessName: $biz->name
        );

        $html = Storage::disk('local')->get("sites/{$deployHome['deploy_hash']}.html");

        $this->assertStringContainsString('href="/sites/'.$biz->id.'/p/distinctive-4931"', $html);

        $hostStr = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $this->assertStringContainsString('<link rel="canonical" href="https://'.$hostStr.'/sites/'.$biz->id.'/p/home">', $html);

        $this->assertStringNotContainsString('href="/distinctive-4931"', $html);
    }

    public function test_the_stable_page_route_serves_the_latest_deployment_of_that_page(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Platform Tenant 2', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $zone = app(PlatformSiteAddressAction::class)->handle($biz->id);

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Distinctive page 4931',
            'slug' => 'distinctive-4931',
        ]);

        $commit = app(SitePublishAction::class)->handle($biz->id, $page->id, []);

        $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $commit['commit_id'],
            businessName: $biz->name
        );

        $response = $this->get("/sites/{$biz->id}/p/distinctive-4931");
        $response->assertStatus(200);
        $html = (string) $response->getContent();
        $this->assertStringContainsString('Distinctive page 4931', $html);

        $page->update(['title' => 'Distinctive second 4932']);
        $commitId2 = 'commit_'.Str::random(16);
        PageVersion::create([
            'business_id' => $biz->id,
            'page_id' => $page->id,
            'commit_id' => $commitId2,
            'content_blocks' => [],
            'pixel_installed' => false,
        ]);

        $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $commitId2,
            businessName: $biz->name
        );

        $response2 = $this->get("/sites/{$biz->id}/p/distinctive-4931");
        $response2->assertStatus(200);
        $html2 = (string) $response2->getContent();
        $this->assertStringContainsString('Distinctive second 4932', $html2);

        $response404 = $this->get("/sites/{$biz->id}/p/no-such-page-4933");
        $response404->assertStatus(404);
    }

    public function test_on_a_verified_custom_domain_links_stay_root_relative(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Custom Domain Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $zone = $this->provisionAction->handle($biz->id, 'acme-roofing.test', true);

        CustomDomainRequest::create([
            'business_id' => $biz->id,
            'domain' => 'acme-roofing.test',
            'status' => 'verified',
        ]);

        $home = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);
        $page2 = Page::create([
            'business_id' => $biz->id,
            'title' => 'Distinctive page 4934',
            'slug' => 'distinctive-4934',
        ]);

        $commitHome = app(SitePublishAction::class)->handle($biz->id, $home->id, []);
        $commit2 = app(SitePublishAction::class)->handle($biz->id, $page2->id, []);

        $deployHome = $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $home->id,
            commitId: $commitHome['commit_id'],
            businessName: $biz->name
        );
        $this->deployAction->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page2->id,
            commitId: $commit2['commit_id'],
            businessName: $biz->name
        );

        $html = Storage::disk('local')->get("sites/{$deployHome['deploy_hash']}.html");

        $this->assertStringContainsString('href="/distinctive-4934"', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://acme-roofing.test/home">', $html);
        $this->assertStringNotContainsString('href="/sites/'.$biz->id.'/p/distinctive-4934"', $html);
    }

    public function test_a_published_page_reads_header_then_content_then_form_then_footer(): void
    {
        Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Framed Tenant 4971', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $site = app(SitePublishAction::class)->handle($biz->id, $page->id, [
            ['type' => 'hero', 'headline' => 'Distinctive framed headline 4972'],
            ['type' => 'form_capture'],
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

        $html = (string) $this->get("/sites/{$biz->id}/{$deploy['deploy_hash']}")->assertStatus(200)->getContent();

        $header = strpos($html, '<header class="site-header">');
        $content = strpos($html, 'Distinctive framed headline 4972');
        $form = strpos($html, 'form-capture-x155');
        $footer = strpos($html, '<footer class="site-footer">');

        $this->assertNotFalse($header);
        $this->assertNotFalse($content);
        $this->assertNotFalse($form);
        $this->assertNotFalse($footer);
        $this->assertTrue($header < $content && $content < $form && $form < $footer, "order: header {$header}, content {$content}, form {$form}, footer {$footer}");
        $this->assertSame(1, substr_count($html, 'form-capture-x155'));
        $this->assertStringContainsString('<p class="site-header__name">Framed Tenant 4971</p>', $html);
    }
}
