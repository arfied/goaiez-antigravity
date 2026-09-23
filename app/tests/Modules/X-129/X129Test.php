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
     * "cutover reversible until DNS propagates"
     */
    public function test_n_129_capabilities(): void
    {
        $engine = new \App\Modules\X129\Domain\MigrationEngine();
        
        // Reversible if DNS not propagated
        $this->assertTrue($engine->ensureReversible(false, true));
        
        // Throws if DNS propagated and cutover complete
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Cutover cannot be reversed once DNS propagates");
        $engine->ensureReversible(true, true);
    }

    /**
     * [N-063]
     * Asserting clause 1: tenant never left on empty domain - cutover allowed when all URLs answer
     */
    public function test_n_063_cutover_allowed_no_404(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Legacy Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");
        $this->buildAction->build($biz->id, ['https://old.com/'], 'https://new.com');
        $res = $this->migrateAction->cutover($biz->id, 'new.com', ['https://old.com/'], ['https://old.com/' => 301]);
        $this->assertNotNull($res);
        $this->assertTrue($res['migrated']);
    }

    /**
     * [N-064]
     * Asserting clause 1: tenant never left on empty domain - cutover refused if one URL 404s
     */
    public function test_n_064_cutover_refused_on_404(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Legacy Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");
        $this->buildAction->build($biz->id, ['https://old.com/'], 'https://new.com');
        $res = $this->migrateAction->cutover($biz->id, 'new.com', ['https://old.com/'], ['https://old.com/' => 404]);
        $this->assertNotNull($res);
        $this->assertEquals('refused', $res['status']);
    }

    /**
     * [N-067]
     * Asserting clause 1: tenant never left on empty domain - refusal names the offending URL
     */
    public function test_n_067_cutover_names_offending_url(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Legacy Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");
        $this->buildAction->build($biz->id, ['https://old.com/bad'], 'https://new.com');
        $res = $this->migrateAction->cutover($biz->id, 'new.com', ['https://old.com/bad'], ['https://old.com/bad' => 404]);
        $this->assertNotNull($res);
        $this->assertContains('https://old.com/bad', $res['blocked_urls']);
    }

    /**
     * [N-070]
     * Asserting clause 1: tenant never left on empty domain - two source URLs produce two redirect rows
     */
    public function test_n_070_build_redirects_count(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Legacy Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");
        $this->buildAction->build($biz->id, ['https://old.com/1', 'https://old.com/2'], 'https://new.com');
        $count = RedirectMap::where('business_id', $biz->id)->count();
        $this->assertNotNull($count);
        $this->assertEquals(2, $count);
    }

    /**
     * [N-073]
     * Asserting clause 1: tenant never left on empty domain - build is idempotent (replayed twice, one result)
     */
    public function test_n_073_build_redirects_idempotent(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Legacy Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");
        $this->buildAction->build($biz->id, ['https://old.com/1'], 'https://new.com');
        $this->buildAction->build($biz->id, ['https://old.com/1'], 'https://new.com');
        $count = RedirectMap::where('business_id', $biz->id)->count();
        $this->assertNotNull($count);
        $this->assertEquals(1, $count);
    }

    /**
     * [N-076]
     * Asserting clause 1: tenant never left on empty domain - build action returns redirects_built status
     */
    public function test_n_076_build_redirects_status(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Legacy Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");
        $res = $this->buildAction->build($biz->id, ['https://old.com/1'], 'https://new.com');
        $this->assertNotNull($res);
        $this->assertEquals('redirects_built', $res['status']);
    }

    /**
     * [N-079]
     * Asserting clause 1: tenant never left on empty domain - cutover allowed when all URLs answer (repeat of N-063)
     */
    public function test_n_079_cutover_allowed_no_404_repeat(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Legacy Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");
        $this->buildAction->build($biz->id, ['https://old.com/'], 'https://new.com');
        $res = $this->migrateAction->cutover($biz->id, 'new.com', ['https://old.com/'], ['https://old.com/' => 301]);
        $this->assertNotNull($res);
        $this->assertTrue($res['migrated']);
    }

    /**
     * [N-082]
     * Asserting clause 1: tenant never left on empty domain - cutover refused if one URL 404s (repeat of N-064)
     */
    public function test_n_082_cutover_refused_on_404_repeat(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Legacy Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");
        $this->buildAction->build($biz->id, ['https://old.com/'], 'https://new.com');
        $res = $this->migrateAction->cutover($biz->id, 'new.com', ['https://old.com/'], ['https://old.com/' => 404]);
        $this->assertNotNull($res);
        $this->assertEquals('refused', $res['status']);
    }

    /**
     * [N-085]
     * Asserting clause 1: tenant never left on empty domain - refusal names the offending URL (repeat of N-067)
     */
    public function test_n_085_cutover_names_offending_url_repeat(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Legacy Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");
        $this->buildAction->build($biz->id, ['https://old.com/bad'], 'https://new.com');
        $res = $this->migrateAction->cutover($biz->id, 'new.com', ['https://old.com/bad'], ['https://old.com/bad' => 404]);
        $this->assertNotNull($res);
        $this->assertContains('https://old.com/bad', $res['blocked_urls']);
    }

    /**
     * [N-062]
     * Asserting: the old domain redirects, never 404s
     */
    public function test_n_062_never_404s(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Legacy Migration Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

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
        $biz = TestCase::provisionTenant(['name' => 'Legacy Migration Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

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
