<?php

declare(strict_types=1);

namespace Tests\Modules\X157;

use App\Enums\IndexingEngine;
use App\Enums\IndexingStatus;
use App\Enums\UserRole;
use App\Jobs\Sites\AnnounceHostedPageJob;
use App\Models\IndexingSubmission;
use App\Models\Location;
use App\Models\User;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVersion;
use App\Modules\X157\Actions\EdgeDeployAction;
use App\Modules\X157\Actions\EdgeProvisionAction;
use App\Modules\X157\Domain\DnsResolver;
use App\Services\Indexing\HostedIndexNowKeys;
use App\Services\Indexing\Indexing;
use App\Services\Tenant\LocationWebsite;
use App\Support\Tenancy;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

/**
 * A page published on a site we host is announced to the IndexNow engines (owner, 2026-10-05), and the custom domain's robots.txt
 * names the domain's own sitemap.
 */
class AnnounceHostedPageTest extends TestCase
{
    use RefreshesTenantDatabase;

    private const HOST = 'acme-roofing-9991.test';

    /**
     * Fixture taken from VariantCookieServingTest::setUp: a business with a verified custom domain and a deployed home page; plus
     * a location whose website the owner confirmed (LocationWebsite::confirm, the only writer of that column).
     *
     * @return array{0: int, 1: Location}
     */
    private function hostedSite(bool $confirmWebsite): array
    {
        app()->instance(DnsResolver::class, new class implements DnsResolver
        {
            public function cname(string $domain): ?string
            {
                return $domain === 'acme-roofing-9991.test' ? parse_url(config('app.url'), PHP_URL_HOST) : null;
            }
        });
        Storage::fake('local');
        Queue::fake();
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['name' => 'Hosted Roofing 9992', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        Tenancy::set((int) $biz->id);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $zone = app(EdgeProvisionAction::class)->handle($biz->id, self::HOST, true);
        DB::table('custom_domain_requests')->where('domain', self::HOST)->delete();
        DB::table('custom_domain_requests')->insert([
            'business_id' => $biz->id,
            'domain' => self::HOST,
            'status' => 'verified',
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $location = Location::factory()->create(['business_id' => $biz->id, 'name' => 'Main 9993']);
        if ($confirmWebsite) {
            app(LocationWebsite::class)->confirm($location, 'https://'.self::HOST, 'user:'.$owner->id, true);
        }

        $page = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home', 'is_published' => true]);
        $commitId = 'commit_'.Str::random(16);
        $version = PageVersion::create([
            'business_id' => $biz->id,
            'page_id' => $page->id,
            'commit_id' => $commitId,
            'content_blocks' => [['type' => 'hero', 'headline' => 'Roofs done right 9994']],
        ]);
        $page->update(['current_version_id' => $version->id]);
        app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $commitId,
            businessName: $biz->name
        );

        return [(int) $biz->id, $location->refresh()];
    }

    public function test_a_page_published_on_a_hosted_domain_is_announced_to_indexnow_and_recorded(): void
    {
        [$bizId, $location] = $this->hostedSite(true);
        $url = 'https://'.self::HOST.'/home';
        Queue::assertPushed(AnnounceHostedPageJob::class, fn (AnnounceHostedPageJob $job): bool => $job->businessId === $bizId && $job->url === $url);

        Http::preventStrayRequests();
        Http::fake([
            'api.indexnow.org/*' => Http::response('', 200),
            '*/robots.txt' => Http::response("User-agent: *\nAllow: /\nSitemap: https://".self::HOST."/sitemap.xml\n", 200, ['Content-Type' => 'text/plain']),
        ]);
        (new AnnounceHostedPageJob($bizId, $url))->handle(app(Indexing::class));

        Http::assertSent(fn (Request $r): bool => $r->url() === 'https://api.indexnow.org/indexnow'
            && $r['host'] === self::HOST
            && $r['key'] === HostedIndexNowKeys::key()
            && $r['keyLocation'] === 'https://'.self::HOST.'/'.HostedIndexNowKeys::key().'.txt'
            && $r['urlList'] === [$url]);
        Tenancy::set($bizId);
        $this->assertSame(1, IndexingSubmission::query()
            ->where('location_id', $location->id)
            ->where('engine', IndexingEngine::IndexNow->value)
            ->where('status', IndexingStatus::Submitted->value)
            ->where('url', $url)
            ->count());
    }

    public function test_a_hosted_page_no_location_claims_is_not_announced(): void
    {
        [$bizId] = $this->hostedSite(false);

        Http::preventStrayRequests();
        Http::fake(['api.indexnow.org/*' => Http::response('', 200)]);
        (new AnnounceHostedPageJob($bizId, 'https://'.self::HOST.'/home'))->handle(app(Indexing::class));

        Http::assertNothingSent();
    }

    public function test_the_custom_domains_robots_names_its_own_sitemap(): void
    {
        $this->hostedSite(true);
        Tenancy::forgetAll();

        $robots = (string) $this->get('http://'.self::HOST.'/robots.txt')->assertOk()->getContent();
        $this->assertStringContainsString('Sitemap: https://'.self::HOST."/sitemap.xml\n", $robots);
        $this->assertStringContainsString("Allow: /\n", $robots);
        $this->assertStringNotContainsString('/sitemap.xml', str_replace('https://'.self::HOST.'/sitemap.xml', '', $robots));
    }
}
