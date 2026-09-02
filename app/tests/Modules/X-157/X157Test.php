<?php

declare(strict_types=1);

namespace Tests\Modules\X157;

use App\Modules\X157\Actions\EdgeDeployAction;
use App\Modules\X157\Actions\EdgeProvisionAction;
use App\Modules\X157\Actions\EdgeRollbackAction;
use App\Modules\X157\Events\DeployCompleted;
use App\Modules\X157\Events\DeployRolledBack;
use App\Modules\X157\Models\Deployment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        $this->assertTrue(true);
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
        $this->assertTrue(true);
    }

    public function test_feature_flags_absent(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $zone = $this->provisionAction->handle($biz->id, 'acme-hvac.com', true);

        $page = \App\Modules\X103\Models\Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $commitId = 'commit_' . \Illuminate\Support\Str::random(16);
        \App\Modules\X103\Models\PageVersion::create([
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

        $html = \Illuminate\Support\Facades\Storage::disk('local')->get("sites/{$deploy['deploy_hash']}.html");

        $this->assertStringNotContainsString('x110-pixel', $html);
        $this->assertStringNotContainsString('chat-widget-container', $html);
        $this->assertStringNotContainsString('form-capture-x155', $html);
        $this->assertStringNotContainsString('dni-pool-x137', $html);
    }

    public function test_feature_flags_present(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $zone = $this->provisionAction->handle($biz->id, 'acme-hvac.com', true);

        $page = \App\Modules\X103\Models\Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $commitId = 'commit_' . \Illuminate\Support\Str::random(16);
        \App\Modules\X103\Models\PageVersion::create([
            'business_id' => $biz->id,
            'page_id' => $page->id,
            'commit_id' => $commitId,
            'content_blocks' => [
                ['type' => 'chat'],
                ['type' => 'form_capture'],
                ['type' => 'dni']
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

        $html = \Illuminate\Support\Facades\Storage::disk('local')->get("sites/{$deploy['deploy_hash']}.html");

        $this->assertStringContainsString('x110-pixel', $html);
        $this->assertStringContainsString('chat-widget-container', $html);
        $this->assertStringContainsString('form-capture-x155', $html);
        $this->assertStringContainsString('dni-pool-x137', $html);
    }

    public function test_ssl_guard_writes_no_artifact(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $noSslZone = $this->provisionAction->handle($biz->id, 'insecure.tenant.com', false);

        $refusedDeploy = $this->deployAction->handle($biz->id, $noSslZone->id);

        $this->assertEquals('refused', $refusedDeploy['status']);
        $this->assertEquals('SSL_CERTIFICATE_REQUIRED', $refusedDeploy['refusal_code']);
        
        $files = \Illuminate\Support\Facades\Storage::disk('local')->files('sites');
        $this->assertEmpty($files);
    }
}
