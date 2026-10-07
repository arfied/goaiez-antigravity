<?php

namespace Tests\Modules\X157;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X103\Actions\SitePublishAction;
use App\Modules\X103\Models\Page;
use App\Modules\X157\Actions\EdgeDeployAction;
use App\Modules\X157\Actions\EdgeProvisionAction;
use App\Modules\X157\Actions\StaticSiteDeployAction;
use App\Modules\X157\Domain\DnsResolver;
use App\Modules\X157\Models\Deployment;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class StaticSiteDeployTest extends TestCase
{
    use RefreshesTenantDatabase;

    private $biz;

    private $zone;

    private $artifactDir;

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

                return null;
            }
        });

        Storage::fake('local');
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        DB::statement("SET app.business_id = '{$this->biz->id}'");

        $this->zone = app(EdgeProvisionAction::class)->handle($this->biz->id, 'acme-roofing.test', true);

        DB::table('custom_domain_requests')->where('domain', 'acme-roofing.test')->delete();
        DB::table('custom_domain_requests')->insert([
            'business_id' => $this->biz->id,
            'domain' => 'acme-roofing.test',
            'status' => 'verified',
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->artifactDir && is_dir($this->artifactDir)) {
            File::deleteDirectory($this->artifactDir);
        }
        parent::tearDown();
    }

    private function artifact(array $files): string
    {
        $dir = sys_get_temp_dir().'/x157-static-'.Str::random(8);
        File::ensureDirectoryExists($dir);
        foreach ($files as $rel => $content) {
            $abs = $dir.'/'.$rel;
            File::ensureDirectoryExists(dirname($abs));
            file_put_contents($abs, $content);
        }
        $this->artifactDir = $dir;

        return $dir;
    }

    private function standardArtifact(): string
    {
        return $this->artifact([
            'index.html' => '<h1>Static Home 7731</h1>',
            'about/index.html' => '<h1>About 7732</h1>',
            'assets/entries/e.js' => 'console.log(1)',
            'assets/static/s.css' => 'body{}',
            'assets/pic.png' => '1234',
            '.vite/manifest.json' => '{}',
            'bad.exe' => '1',
        ]);
    }

    public function test_a_static_artifact_deploys_and_serves_pages_and_assets_with_their_types(): void
    {
        $dir = $this->standardArtifact();
        $hash = StaticSiteDeployAction::newHash();

        $res = app(StaticSiteDeployAction::class)->handle($this->biz->id, $this->zone->id, $dir, $hash);

        $this->assertEquals('deployed', $res['status']);
        $this->assertEquals(5, $res['files']);
        $this->assertEquals(2, $res['skipped']);

        $response = $this->get("/sites/{$this->biz->id}/{$hash}/");
        $response->assertStatus(200);
        $this->assertStringStartsWith('text/html', $response->headers->get('Content-Type'));
        $response->assertSee('Static Home 7731');

        $response = $this->get("/sites/{$this->biz->id}/{$hash}/about");
        $response->assertStatus(200);
        $response->assertSee('About 7732');

        $response = $this->get("/sites/{$this->biz->id}/{$hash}/assets/static/s.css");
        $response->assertStatus(200);
        $this->assertStringStartsWith('text/css', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('immutable', $response->headers->get('Cache-Control'));

        $response = $this->get("/sites/{$this->biz->id}/{$hash}/assets/pic.png");
        $response->assertStatus(200);
        $this->assertStringStartsWith('image/png', $response->headers->get('Content-Type'));
    }

    public function test_the_platform_root_route_serves_a_static_deployment_index(): void
    {
        $dir = $this->standardArtifact();
        $hash = StaticSiteDeployAction::newHash();
        app(StaticSiteDeployAction::class)->handle($this->biz->id, $this->zone->id, $dir, $hash);

        $response = $this->get("/sites/{$this->biz->id}/{$hash}");
        $response->assertStatus(200);
        $response->assertSee('Static Home 7731');
    }

    public function test_paths_outside_the_artifact_and_unknown_types_are_refused(): void
    {
        $dir = $this->standardArtifact();
        $hash = StaticSiteDeployAction::newHash();
        app(StaticSiteDeployAction::class)->handle($this->biz->id, $this->zone->id, $dir, $hash);

        $this->assertNotEquals(200, $this->get("/sites/{$this->biz->id}/{$hash}/../index.html")->status());
        $this->get("/sites/{$this->biz->id}/{$hash}/.vite/manifest.json")->assertNotFound();
        $this->get("/sites/{$this->biz->id}/{$hash}/bad.exe")->assertNotFound();
        $this->get("/sites/{$this->biz->id}/{$hash}/missing.js")->assertNotFound();

        $this->assertFalse(Storage::disk('local')->exists("sites-static/{$hash}/bad.exe"));
    }

    public function test_a_second_static_deploy_supersedes_the_first(): void
    {
        $dir = $this->standardArtifact();
        $hash1 = StaticSiteDeployAction::newHash();
        app(StaticSiteDeployAction::class)->handle($this->biz->id, $this->zone->id, $dir, $hash1);

        $hash2 = StaticSiteDeployAction::newHash();
        app(StaticSiteDeployAction::class)->handle($this->biz->id, $this->zone->id, $dir, $hash2);

        $row1 = Deployment::where('deploy_hash', $hash1)->first();
        $this->assertEquals('superseded', $row1->status);

        $this->get("/sites/{$this->biz->id}/{$hash1}/")->assertNotFound();
        $this->get("/sites/{$this->biz->id}/{$hash2}/")->assertStatus(200);
    }

    public function test_a_static_deploy_is_refused_without_ssl_and_writes_nothing(): void
    {
        $this->zone->update(['has_valid_ssl' => false]);
        $dir = $this->standardArtifact();
        $hash = StaticSiteDeployAction::newHash();

        $res = app(StaticSiteDeployAction::class)->handle($this->biz->id, $this->zone->id, $dir, $hash);

        $this->assertEquals('refused', $res['status']);
        $this->assertEquals('SSL_CERTIFICATE_REQUIRED', $res['refusal_code']);
        $this->assertEquals(0, Deployment::where('deploy_hash', $hash)->count());
        $this->assertFalse(Storage::disk('local')->exists("sites-static/{$hash}/index.html"));
    }

    public function test_a_static_deploy_is_refused_without_an_index_or_over_the_size_seed(): void
    {
        $dir = $this->artifact(['about/index.html' => '<h1>About</h1>']);
        $hash = StaticSiteDeployAction::newHash();
        $res = app(StaticSiteDeployAction::class)->handle($this->biz->id, $this->zone->id, $dir, $hash);
        $this->assertEquals('NO_INDEX', $res['refusal_code']);

        app(DefaultsRegistry::class)->set('sites.deploy.static_max_bytes', 10, 'test');
        $dir2 = $this->standardArtifact();
        $hash2 = StaticSiteDeployAction::newHash();
        $res2 = app(StaticSiteDeployAction::class)->handle($this->biz->id, $this->zone->id, $dir2, $hash2);
        $this->assertEquals('ARTIFACT_TOO_LARGE', $res2['refusal_code']);
        $this->assertEquals(0, Deployment::where('deploy_hash', $hash2)->count());
    }

    public function test_a_verified_custom_domain_serves_the_static_site_at_its_root(): void
    {
        $dir = $this->standardArtifact();
        $hash = StaticSiteDeployAction::newHash();
        app(StaticSiteDeployAction::class)->handle($this->biz->id, $this->zone->id, $dir, $hash);

        Tenancy::forgetAll();

        $response = $this->get('http://acme-roofing.test/');
        $response->assertStatus(200);
        $response->assertSee('Static Home 7731');

        $response = $this->get('http://acme-roofing.test/about');
        $response->assertStatus(200);
        $response->assertSee('About 7732');

        $response = $this->get('http://acme-roofing.test/assets/static/s.css');
        $response->assertStatus(200);
        $this->assertStringStartsWith('text/css', $response->headers->get('Content-Type'));

        $this->get('http://acme-roofing.test/nope.css')->assertNotFound();
    }

    public function test_a_page_deploy_still_serves_and_reads_kind_page(): void
    {
        $page = Page::create(['business_id' => $this->biz->id, 'title' => 'Home', 'slug' => 'home']);
        $site = app(SitePublishAction::class)->handle($this->biz->id, $page->id, []);

        app(EdgeDeployAction::class)->handle(
            $this->biz->id,
            $this->zone->id,
            100,
            1500,
            $page->id,
            $site['commit_id'],
            'Edge Tenant'
        );

        $row = Deployment::where('business_id', $this->biz->id)->latest('id')->first();
        $this->assertEquals('page', $row->kind);

        $response = $this->get("/sites/{$this->biz->id}/{$row->deploy_hash}");
        $response->assertStatus(200);
    }

    public function test_an_unknown_path_on_the_platform_host_still_answers_404(): void
    {
        $this->get('/no-such-path-7734')->assertNotFound();
    }
}
