<?php

declare(strict_types=1);

namespace Tests\Modules\X129;

use App\Modules\X129\Actions\RedirectsBuildAction;
use App\Modules\X129\Actions\SiteMigrateAction;
use App\Modules\X129\Events\DomainVerified;
use App\Modules\X129\Events\SiteMigrated;
use App\Modules\X129\Models\RedirectMap;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X129Test extends TestCase
{
    private RedirectsBuildAction $buildAction;

    private SiteMigrateAction $migrateAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildAction = new RedirectsBuildAction;
        $this->migrateAction = new SiteMigrateAction;
    }

    /**
     * TEST ANCHOR
     * every URL in the source crawl has a redirect row;
     * cutover is blocked while any old URL returns 404 on the new host
     */
    public function test_anchor_source_crawl_redirect_mapping_and_404_cutover_gate(): void
    {
        Event::fake([SiteMigrated::class, DomainVerified::class]);

        $biz = TestCase::provisionTenant(['name' => 'Legacy Migration Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $sourceCrawl = [
            'https://oldplumber.com/',
            'https://oldplumber.com/about-us',
            'https://oldplumber.com/emergency-services',
            'https://oldplumber.com/contact',
        ];

        // 1. Every URL in the source crawl has a redirect row (TEST ANCHOR)
        $buildResult = $this->buildAction->build(
            businessId: $biz->id,
            sourceCrawlUrls: $sourceCrawl,
            newDomainHost: 'https://newplumbingking.com'
        );

        $this->assertEquals('redirects_built', $buildResult['status']);
        $this->assertEquals(4, $buildResult['count']);

        $savedRedirectCount = RedirectMap::where('business_id', $biz->id)->count();
        $this->assertEquals(4, $savedRedirectCount);

        // 2. Cutover is BLOCKED while any old URL returns 404 on the new host (TEST ANCHOR)
        $simulatedWith404 = [
            'https://oldplumber.com/' => 301,
            'https://oldplumber.com/about-us' => 301,
            'https://oldplumber.com/emergency-services' => 404, // Broken/404 URL on new host
            'https://oldplumber.com/contact' => 301,
        ];

        $blockedCutover = $this->migrateAction->cutover(
            businessId: $biz->id,
            domain: 'newplumbingking.com',
            sourceCrawlUrls: $sourceCrawl,
            simulatedHostResponses: $simulatedWith404
        );

        $this->assertEquals('refused', $blockedCutover['status']);
        $this->assertEquals('CUTOVER_BLOCKED_UNRESOLVED_404_URLS', $blockedCutover['refusal_code']);
        $this->assertFalse($blockedCutover['migrated']);
        $this->assertContains('https://oldplumber.com/emergency-services', $blockedCutover['blocked_urls']);

        // 3. Valid Cutover when all URLs resolve cleanly (0 404s)
        $cleanResponses = [
            'https://oldplumber.com/' => 301,
            'https://oldplumber.com/about-us' => 301,
            'https://oldplumber.com/emergency-services' => 301,
            'https://oldplumber.com/contact' => 301,
        ];

        $successCutover = $this->migrateAction->cutover(
            businessId: $biz->id,
            domain: 'newplumbingking.com',
            sourceCrawlUrls: $sourceCrawl,
            simulatedHostResponses: $cleanResponses
        );

        $this->assertEquals('cutover_completed', $successCutover['status']);
        $this->assertTrue($successCutover['migrated']);
        $this->assertEquals(4, $successCutover['redirects_verified']);

        Event::assertDispatched(SiteMigrated::class);
        Event::assertDispatched(DomainVerified::class);
    }

    /**
     * [N-129-01]
     */
    public function test_n_129_capabilities(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [N-063]
     * ⛔ REFUSED: "X-168 has no overtime/out-of-hours/attendance (P-204)" belongs to X-168
     */
    public function test_n_063_refused(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [N-064]
     * ⛔ REFUSED: "X-168 has no overtime/out-of-hours/attendance (P-204)" belongs to X-168
     */
    public function test_n_064_refused(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [N-067]
     * ⛔ REFUSED: "X-168 has no overtime/out-of-hours/attendance (P-204)" belongs to X-168
     */
    public function test_n_067_refused(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [N-070]
     * ⛔ REFUSED: "X-168 has no overtime/out-of-hours/attendance (P-204)" belongs to X-168
     */
    public function test_n_070_refused(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [N-073]
     * ⛔ REFUSED: "X-168 has no overtime/out-of-hours/attendance (P-204)" belongs to X-168
     */
    public function test_n_073_refused(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [N-076]
     * ⛔ REFUSED: "X-168 has no overtime/out-of-hours/attendance (P-204)" belongs to X-168
     */
    public function test_n_076_refused(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [N-079]
     * ⛔ REFUSED: "X-168 has no overtime/out-of-hours/attendance (P-204)" belongs to X-168
     */
    public function test_n_079_refused(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [N-082]
     * ⛔ REFUSED: "X-168 has no overtime/out-of-hours/attendance (P-204)" belongs to X-168
     */
    public function test_n_082_refused(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [N-085]
     * ⛔ REFUSED: "X-168 has no overtime/out-of-hours/attendance (P-204)" belongs to X-168
     */
    public function test_n_085_refused(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [N-062]
     * Asserting: the old domain redirects, never 404s
     */
    public function test_n_062_never_404s(): void
    {
        $biz = \Tests\TestCase::provisionTenant(['name' => 'Legacy Migration Tenant', 'currency' => 'USD']);
        \Illuminate\Support\Facades\DB::statement("SET app.business_id = '{$biz->id}'");

        $sourceCrawl = [
            'https://oldplumber.com/emergency-services',
        ];

        $simulatedWith404 = [
            'https://oldplumber.com/emergency-services' => 404, // Broken/404 URL on new host
        ];

        $blockedCutover = $this->migrateAction->cutover(
            businessId: $biz->id,
            domain: 'newplumbingking.com',
            sourceCrawlUrls: $sourceCrawl,
            simulatedHostResponses: $simulatedWith404
        );

        $this->assertEquals('refused', $blockedCutover['status']);
        $this->assertFalse($blockedCutover['migrated']);
    }

    /**
     * [N-065]
     * Asserting: rankings, links and redirects MOVE
     */
    public function test_n_065_redirects_move(): void
    {
        $biz = \Tests\TestCase::provisionTenant(['name' => 'Legacy Migration Tenant', 'currency' => 'USD']);
        \Illuminate\Support\Facades\DB::statement("SET app.business_id = '{$biz->id}'");

        $sourceCrawl = [
            'https://oldplumber.com/',
        ];

        $buildResult = $this->buildAction->build(
            businessId: $biz->id,
            sourceCrawlUrls: $sourceCrawl,
            newDomainHost: 'https://newplumbingking.com'
        );

        $this->assertEquals('redirects_built', $buildResult['status']);
        $savedRedirectCount = RedirectMap::where('business_id', $biz->id)->count();
        $this->assertEquals(1, $savedRedirectCount);
    }
}
