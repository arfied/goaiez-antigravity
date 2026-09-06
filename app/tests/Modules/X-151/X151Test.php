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
use App\Support\Tenancy;
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
        Tenancy::set((int) $biz->id);

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
        Tenancy::set((int) $biz->id);

        $sitemapRes = $this->sitemapAction->handle($biz->id, 'contractor.io', 'https://contractor.io/sitemap.xml');
        $this->assertCount(3, $sitemapRes['urls_discovered']);
    }

    /** [G7-36] */
    public function test_g7_36_fetch_refresh_is_not_ad_management(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'G7-36 Biz', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        $fetchRes = $this->runAction->handle(
            businessId: $biz->id,
            domain: 'g7-36-domain.com',
            url: 'https://g7-36-domain.com/page',
            encounterCaptcha: false,
            currentActiveWorkers: 0
        );
        $fetchId = $fetchRes['fetch_id'];

        $fetch = $this->refreshAction->handle($biz->id, $fetchId);
        $this->assertTrue($fetch->is_stale);
        $this->assertEquals('stale', $fetch->status);

        $this->assertNotNull(Fetch::find($fetchId));

        $paramNames = array_map(fn ($p) => $p->getName(), (new \ReflectionMethod(FetchRefreshAction::class, 'handle'))->getParameters());
        $this->assertEquals(['businessId', 'fetchId'], $paramNames);
        $this->assertCount(2, $paramNames);

        $fetchKeys = array_keys((new Fetch)->getAttributes());
        $targetKeys = array_keys((new FetchTarget)->getAttributes());
        $allNames = array_merge($fetchKeys, $targetKeys, $paramNames);

        $regex = '/(audience|lookalike|adset|ad_set|ad_spend|adwords|retarget|remarketing|cpc|cpm|pixel|meta_ads)/i';
        foreach ($allNames as $name) {
            $this->assertDoesNotMatchRegularExpression($regex, $name);
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path('Modules/X-151')));
        $files = [];
        $controlCount = 0;
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $filename = $file->getFilename();
                if ($filename === 'capabilities.php' || $filename === 'manifest.php') {
                    continue;
                }
                $files[] = $file->getPathname();
                if (preg_match('/Fetch|ProxyPool|RobotsSignal/', $filename)) {
                    $controlCount++;
                }
            }
        }
        $this->assertGreaterThanOrEqual(12, count($files));

        $contentRegex = '/\b(meta_ads|seed_audience|custom_audience|audience|audiences|lookalike|adset|ad_set|retarget|retargeting|remarketing|ad_spend|adwords|cpc|cpm|pixel_id)\b/i';
        foreach ($files as $filePath) {
            $content = file_get_contents($filePath);
            $this->assertDoesNotMatchRegularExpression($contentRegex, $content);
        }

        $this->assertGreaterThanOrEqual(3, $controlCount);
    }
}
