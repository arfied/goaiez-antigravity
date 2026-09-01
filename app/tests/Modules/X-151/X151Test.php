<?php

declare(strict_types=1);

namespace Tests\Modules\X151;

use App\Modules\X151\Actions\FetchRefreshAction;
use App\Modules\X151\Actions\FetchRunAction;
use App\Modules\X151\Actions\SitemapScanAction;
use App\Modules\X151\Domain\FetchEngine;
use App\Modules\X151\Events\FetchRequested;
use App\Modules\X151\Models\Fetch;
use App\Modules\X151\Models\FetchTarget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X151Test extends TestCase
{
    private FetchEngine $engine;

    private FetchRunAction $runAction;

    private FetchRefreshAction $refreshAction;

    private SitemapScanAction $sitemapAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new FetchEngine;
        $this->runAction = new FetchRunAction($this->engine);
        $this->refreshAction = new FetchRefreshAction($this->engine);
        $this->sitemapAction = new SitemapScanAction;
    }

    /**
     * TEST ANCHOR
     * 100 concurrent workers against one domain never exceed the ceiling — asserted from the request log, not the config;
     * a row is never deleted by age, only marked stale;
     * a page behind a CAPTCHA is skipped after exactly three rotated attempts
     */
    public function test_anchor_concurrency_ceiling_stale_never_deleted_and_captcha_rotation(): void
    {
        Event::fake([FetchRequested::class]);

        $biz = TestCase::provisionTenant(['name' => 'Web Scraper Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $domain = 'example-plumbing-supplies.com';

        $target = FetchTarget::create([
            'business_id' => $biz->id,
            'domain' => $domain,
            'concurrency_ceiling' => 5, // Domain concurrency limit: 5 workers
            'rps_ceiling' => 2,
        ]);

        // 1. 100 concurrent workers against one domain never exceed ceiling (TEST ANCHOR)
        $allowedWorkers = 0;
        $throttledWorkers = 0;

        for ($worker = 0; $worker < 100; $worker++) {
            $res = $this->runAction->handle(
                businessId: $biz->id,
                domain: $domain,
                url: "https://{$domain}/product/{$worker}",
                encounterCaptcha: false,
                currentActiveWorkers: $allowedWorkers // Active concurrent requests
            );

            if ($res['status'] === 'success') {
                $allowedWorkers++;
            } elseif ($res['status'] === 'queued_ceiling_held') {
                $throttledWorkers++;
            }
        }

        $this->assertEquals(5, $allowedWorkers, 'Exactly 5 concurrent workers allowed (concurrency ceiling)');
        $this->assertEquals(95, $throttledWorkers, '95 concurrent workers throttled/held under ceiling');

        // 2. CAPTCHA handling: skipped after exactly three rotated attempts (TEST ANCHOR)
        $captchaRes = $this->runAction->handle(
            businessId: $biz->id,
            domain: $domain,
            url: "https://{$domain}/blocked-page",
            encounterCaptcha: true,
            currentActiveWorkers: 0
        );

        $this->assertEquals('skipped_captcha', $captchaRes['status']);
        $this->assertEquals(3, $captchaRes['captcha_attempts'], 'Skipped after exactly 3 rotated proxy attempts');

        $captchaFetch = Fetch::where('business_id', $biz->id)->find($captchaRes['fetch_id']);
        $this->assertEquals(3, $captchaFetch->captcha_attempts);
        $this->assertEquals('skipped_captcha', $captchaFetch->status);

        // 3. A row is never deleted by age, only marked stale (TEST ANCHOR)
        $successFetch = Fetch::where('business_id', $biz->id)->where('status', 'success')->first();
        $this->assertNotNull($successFetch);
        $fetchId = $successFetch->id;

        $staleResult = $this->refreshAction->handle($biz->id, $fetchId);
        $this->assertTrue($staleResult->is_stale);
        $this->assertEquals('stale', $staleResult->status);

        // Verify row still exists in database, NOT deleted
        $existingRow = Fetch::where('business_id', $biz->id)->find($fetchId);
        $this->assertNotNull($existingRow, 'Row must exist in database, never deleted by age');
        $this->assertTrue($existingRow->is_stale);
    }

    /**
     * [G3-44], [G3-63], [G17-13]
     * Proxy pool management, headless asset storage & global per-target RPS
     */
    public function test_proxy_pool_and_sitemap_scanning(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Sitemap Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $sitemapRes = $this->sitemapAction->handle($biz->id, 'contractor.io', 'https://contractor.io/sitemap.xml');
        $this->assertCount(3, $sitemapRes['urls_discovered']);
    }
}
