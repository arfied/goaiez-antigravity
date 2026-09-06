<?php

declare(strict_types=1);

namespace Tests\Modules\X157;

use App\Modules\X103\Actions\SitePublishAction;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVersion;
use App\Modules\X157\Actions\EdgeDeployAction;
use App\Modules\X157\Actions\EdgeProvisionAction;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SslDerivedTest extends TestCase
{
    public function test_ssl_installed_is_derived_and_falsifiable(): void
    {
        $biz = self::provisionTenant(['name' => 'SSL Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'ssl-test.com', true);

        $site = app(SitePublishAction::class)->handle($biz->id, $page->id, []);
        $version = PageVersion::find($site['version_id']);

        $deploy = app(EdgeDeployAction::class)->handle($biz->id, $zone->id, 100, 1500, $page->id, $site['commit_id'], 'SSL Tenant');

        $version->refresh();
        $this->assertTrue($version->ssl_installed);

        $response = $this->get("/sites/{$biz->id}/{$deploy['deploy_hash']}");
        $response->assertStatus(200);

        // FLIP
        $zone->update(['has_valid_ssl' => false]);
        $zone->refresh();
        $refusedDeploy = app(EdgeDeployAction::class)->handle($biz->id, $zone->id, 100, 1500, $page->id, $site['commit_id'], 'SSL Tenant');

        $version->refresh();
        $this->assertFalse($version->ssl_installed);

        $response = $this->get("/sites/{$biz->id}/{$deploy['deploy_hash']}");
        $response->assertStatus(404);
    }
}
