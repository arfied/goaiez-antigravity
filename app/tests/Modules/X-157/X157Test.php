<?php

declare(strict_types=1);

namespace Tests\Modules\X157;

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
use Tests\TestCase;

class X157Test extends TestCase
{
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

        $this->get("/sites/{$deploy['deploy_hash']}")->assertOk();
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

        $response = $this->get('/sites/test_hash_1');
        $response->assertStatus(200);
        $this->assertEquals('BODY_CONTENT', $response->getContent());
    }

    public function test_route_unknown_hash_returns_404(): void
    {
        $response = $this->get('/sites/unknown_hash_999');
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

        $response = $this->get('/sites/test_hash_no_ssl');
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
}
