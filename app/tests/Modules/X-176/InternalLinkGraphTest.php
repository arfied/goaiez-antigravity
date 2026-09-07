<?php

declare(strict_types=1);

namespace Tests\Modules\X176;

use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVersion;
use App\Modules\X157\Actions\EdgeDeployAction;
use App\Modules\X157\Actions\EdgeProvisionAction;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class InternalLinkGraphTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * [G8-02]
     */
    public function test_generator_renders_internal_links_when_hierarchy_published(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Internal Link Tenant 1']);
        Tenancy::set((int) $biz->id);

        $rootPage = Page::create(['business_id' => $biz->id, 'title' => 'Services', 'slug' => 'services', 'is_published' => true]);
        Page::create(['business_id' => $biz->id, 'title' => 'Plumbing', 'slug' => 'services/plumbing', 'is_published' => true]);
        Page::create(['business_id' => $biz->id, 'title' => 'Emergency', 'slug' => 'services/plumbing/emergency', 'is_published' => true]);

        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'links1.example.com', true);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $rootPage->id,
            commitId: 'commit_test',
            businessName: 'My Biz 1'
        );

        $this->assertEquals('deployed', $res['status']);
        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        $this->assertStringContainsString('<nav id="internal-links-x176">', $html);

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $links = $xpath->query('//nav[@id="internal-links-x176"]//a');
        $hrefs = [];
        foreach ($links as $link) {
            $hrefs[] = $link->getAttribute('href');
        }

        $this->assertEqualsCanonicalizing([
            '/services',
            '/services/plumbing',
            '/services/plumbing/emergency',
        ], $hrefs);
    }

    /**
     * [G8-25]
     */
    public function test_graph_property_is_acyclic_and_reachable(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Internal Link Tenant 2']);
        Tenancy::set((int) $biz->id);

        $rootPage = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => '/', 'is_published' => true]);
        Page::create(['business_id' => $biz->id, 'title' => 'Services', 'slug' => 'services', 'is_published' => true]);
        Page::create(['business_id' => $biz->id, 'title' => 'Plumbing', 'slug' => 'services/plumbing', 'is_published' => true]);
        Page::create(['business_id' => $biz->id, 'title' => 'Emergency', 'slug' => 'services/plumbing/emergency', 'is_published' => true]);
        Page::create(['business_id' => $biz->id, 'title' => 'About', 'slug' => 'about', 'is_published' => true]);

        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'links2.example.com', true);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $rootPage->id,
            commitId: 'commit_test',
            businessName: 'My Biz 2'
        );

        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $links = $xpath->query('//nav[@id="internal-links-x176"]//a');
        $nodes = [];
        foreach ($links as $link) {
            $nodes[] = $link->getAttribute('href');
        }

        $this->assertContains('/', $nodes);

        $parentsByChild = [];
        foreach ($links as $link) {
            $childHref = $link->getAttribute('href');
            if (! isset($parentsByChild[$childHref])) {
                $parentsByChild[$childHref] = [];
            }
            $parentList = $xpath->query('../../../a', $link);
            if ($parentList->length > 0) {
                $parentHref = $parentList->item(0)->getAttribute('href');
                $parentsByChild[$childHref][] = $parentHref;
            }
        }

        foreach ($nodes as $node) {
            if ($node === '/') {
                $this->assertEmpty($parentsByChild[$node], 'Root should have no parents');
            } else {
                $this->assertCount(1, $parentsByChild[$node], "Node $node should have exactly one parent");
                $this->assertContains($parentsByChild[$node][0], $nodes, "Parent of $node must be a valid node");
            }
        }

        foreach ($nodes as $node) {
            $current = $node;
            $steps = 0;
            while ($current !== '/') {
                $this->assertLessThanOrEqual(count($nodes), $steps, "Graph has a cycle starting from $node");
                $current = $parentsByChild[$current][0];
                $steps++;
            }
            $this->assertEquals('/', $current);
        }
    }

    public function test_falsifier_absence_single_page_omits_nav(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Internal Link Tenant 3']);
        Tenancy::set((int) $biz->id);

        $page = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => '/', 'is_published' => true]);

        PageVersion::create([
            'business_id' => $biz->id,
            'page_id' => $page->id,
            'commit_id' => 'commit_links_1',
            'content_blocks' => [
                ['type' => 'chat_widget'],
                ['type' => 'pixel_script'],
            ],
        ]);

        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'links3.example.com', true);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $page->id,
            commitId: 'commit_links_1',
            businessName: 'My Biz 3'
        );

        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        $this->assertStringContainsString('chat-widget-container', $html);
        $this->assertStringContainsString('x110-pixel', $html);
        $this->assertStringContainsString('application/ld+json', $html);

        $this->assertStringNotContainsString('<nav id="internal-links-x176">', $html);
    }

    public function test_falsifier_exclusion_rule_omits_orphan_includes_resolvable(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Internal Link Tenant 4']);
        Tenancy::set((int) $biz->id);

        $rootPage = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => '/', 'is_published' => true]);
        Page::create(['business_id' => $biz->id, 'title' => 'About', 'slug' => 'about', 'is_published' => true]);
        Page::create(['business_id' => $biz->id, 'title' => 'Orphan', 'slug' => 'a/b/c', 'is_published' => true]);

        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'links4.example.com', true);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $rootPage->id,
            commitId: 'commit_test',
            businessName: 'My Biz 4'
        );

        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        $this->assertStringContainsString('<nav id="internal-links-x176">', $html);

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $links = $xpath->query('//nav[@id="internal-links-x176"]//a');
        $hrefs = [];
        foreach ($links as $link) {
            $hrefs[] = $link->getAttribute('href');
        }

        $this->assertContains('/about', $hrefs);
        $this->assertNotContains('/a/b/c', $hrefs);
    }

    public function test_normalises_leading_slashes_in_slugs(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Internal Link Tenant 5']);
        Tenancy::set((int) $biz->id);

        $rootPage = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => '/', 'is_published' => true]);
        Page::create(['business_id' => $biz->id, 'title' => 'Services', 'slug' => '/services', 'is_published' => true]);
        Page::create(['business_id' => $biz->id, 'title' => 'Plumbing', 'slug' => '/services/plumbing', 'is_published' => true]);

        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'links5.example.com', true);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $rootPage->id,
            commitId: 'commit_test_5',
            businessName: 'My Biz 5'
        );

        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $links = $xpath->query('//nav[@id="internal-links-x176"]//a');
        $hrefs = [];
        foreach ($links as $link) {
            $hrefs[] = $link->getAttribute('href');
        }

        $this->assertEqualsCanonicalizing([
            '/',
            '/services',
            '/services/plumbing',
        ], $hrefs);
    }

    public function test_falsifier_order_internal_links_by_slug_ascending(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Internal Link Tenant 6']);
        Tenancy::set((int) $biz->id);

        $rootPage = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => '/', 'is_published' => true]);
        // Insertion order out of alphabetical order
        Page::create(['business_id' => $biz->id, 'title' => 'Zebra', 'slug' => 'zebra', 'is_published' => true]);
        Page::create(['business_id' => $biz->id, 'title' => 'Apple', 'slug' => 'apple', 'is_published' => true]);
        Page::create(['business_id' => $biz->id, 'title' => 'Banana', 'slug' => 'banana', 'is_published' => true]);

        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'links6.example.com', true);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $rootPage->id,
            commitId: 'commit_test_6',
            businessName: 'My Biz 6'
        );

        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $links = $xpath->query('//nav[@id="internal-links-x176"]//a');
        $hrefs = [];
        foreach ($links as $link) {
            $hrefs[] = $link->getAttribute('href');
        }

        $this->assertSame([
            '/',
            '/apple',
            '/banana',
            '/zebra',
        ], $hrefs);
    }

    public function test_falsifier_cap_emitted_list_at_20(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Internal Link Tenant 7']);
        Tenancy::set((int) $biz->id);

        $rootPage = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => '/', 'is_published' => true]);

        // 25 more pages
        for ($i = 1; $i <= 25; $i++) {
            $slug = 'page-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            Page::create(['business_id' => $biz->id, 'title' => "Page {$i}", 'slug' => $slug, 'is_published' => true]);
        }

        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'links7.example.com', true);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $rootPage->id,
            commitId: 'commit_test_7',
            businessName: 'My Biz 7'
        );

        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $links = $xpath->query('//nav[@id="internal-links-x176"]//a');

        $this->assertCount(20, $links);
    }

    public function test_falsifier_cap_preserves_ancestor_closure_on_dom(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Internal Link Tenant 9']);
        Tenancy::set((int) $biz->id);

        $rootPage = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => '/', 'is_published' => true]);

        Page::create(['business_id' => $biz->id, 'title' => 'Foo Parent', 'slug' => 'foo', 'is_published' => true]);
        Page::create(['business_id' => $biz->id, 'title' => 'Foo Child', 'slug' => '/foo/bar', 'is_published' => true]);

        for ($i = 1; $i <= 20; $i++) {
            $s = 'a-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            Page::create(['business_id' => $biz->id, 'title' => "Pad {$i}", 'slug' => $s, 'is_published' => true]);
        }

        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'links9.example.com', true);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $rootPage->id,
            commitId: 'commit_test_9',
            businessName: 'My Biz 9'
        );

        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $links = $xpath->query('//nav[@id="internal-links-x176"]//a');
        $nodes = [];
        foreach ($links as $link) {
            $nodes[] = $link->getAttribute('href');
        }

        $this->assertContains('/', $nodes);

        $parentsByChild = [];
        foreach ($links as $link) {
            $childHref = $link->getAttribute('href');
            if (! isset($parentsByChild[$childHref])) {
                $parentsByChild[$childHref] = [];
            }
            $parentList = $xpath->query('../../../a', $link);
            if ($parentList->length > 0) {
                $parentHref = $parentList->item(0)->getAttribute('href');
                $parentsByChild[$childHref][] = $parentHref;
            }
        }

        foreach ($nodes as $node) {
            if ($node === '/') {
                $this->assertEmpty($parentsByChild[$node], 'Root should have no parents');
            } else {
                $this->assertCount(1, $parentsByChild[$node], "Node $node should have exactly one parent");
                $this->assertContains($parentsByChild[$node][0], $nodes, "Parent of $node must be a valid node");
            }
        }
    }

    public function test_a_two_deep_page_renders_when_everything_fits(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Internal Link Tenant 8']);
        Tenancy::set((int) $biz->id);

        $rootPage2 = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => '/', 'is_published' => true]);

        Page::create(['business_id' => $biz->id, 'title' => 'Foo Parent', 'slug' => 'foo', 'is_published' => true]);
        Page::create(['business_id' => $biz->id, 'title' => 'Foo Child', 'slug' => '/foo/bar', 'is_published' => true]);

        for ($i = 1; $i <= 17; $i++) {
            $s = 'a-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            Page::create(['business_id' => $biz->id, 'title' => "Pad {$i}", 'slug' => $s, 'is_published' => true]);
        }

        $zone2 = app(EdgeProvisionAction::class)->handle($biz->id, 'links8.example.com', true);

        $res2 = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone2->id,
            pageId: $rootPage2->id,
            commitId: 'commit_test_8',
            businessName: 'My Biz 8'
        );

        $html2 = Storage::disk('local')->get("sites/{$res2['deploy_hash']}.html");

        $dom2 = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom2->loadHTML($html2);
        $xpath2 = new \DOMXPath($dom2);
        $links2 = $xpath2->query('//nav[@id="internal-links-x176"]//a');

        $hrefs2 = [];
        foreach ($links2 as $link) {
            $hrefs2[] = $link->getAttribute('href');
        }

        // Assert that the child is STILL LINKED because the cap is on the emitted list, not the fetch.
        // We dump hrefs to see what is missing
        $this->assertContains('/foo/bar', $hrefs2);
    }

    public function test_f8_nav_collision_refuses_non_root(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Internal Link Tenant F8']);
        Tenancy::set((int) $biz->id);

        $rootPage = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => '/', 'is_published' => true]);

        Page::create(['business_id' => $biz->id, 'title' => 'Services', 'slug' => 'services', 'is_published' => true]);
        Page::create(['business_id' => $biz->id, 'title' => 'Services Slash', 'slug' => '/services', 'is_published' => true]);
        Page::create(['business_id' => $biz->id, 'title' => 'Child', 'slug' => 'services/child', 'is_published' => true]);
        Page::create(['business_id' => $biz->id, 'title' => 'Unrelated 1', 'slug' => 'unrelated-1', 'is_published' => true]);
        Page::create(['business_id' => $biz->id, 'title' => 'Unrelated 2', 'slug' => 'unrelated-2', 'is_published' => true]);

        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'linksf8.example.com', true);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $rootPage->id,
            commitId: 'commit_test_f8',
            businessName: 'My Biz F8'
        );

        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $links = $xpath->query('//nav[@id="internal-links-x176"]//a');
        $hrefs = [];
        foreach ($links as $link) {
            $hrefs[] = $link->getAttribute('href');
        }

        $this->assertNotContains('/services', $hrefs, 'Expected colliding key /services to be absent');
        $this->assertNotContains('/services/child', $hrefs, 'Expected descendant /services/child to be absent');
        $this->assertContains('/unrelated-1', $hrefs, 'Expected unrelated-1 to be present');
        $this->assertContains('/unrelated-2', $hrefs, 'Expected unrelated-2 to be present');
    }

    public function test_f9_nav_collision_refuses_root(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Internal Link Tenant F9']);
        Tenancy::set((int) $biz->id);

        $rootPage = Page::create(['business_id' => $biz->id, 'title' => 'Home Empty', 'slug' => '', 'is_published' => true]);
        Page::create(['business_id' => $biz->id, 'title' => 'Home Slash', 'slug' => '/', 'is_published' => true]);

        $page = Page::create(['business_id' => $biz->id, 'title' => 'Unrelated 1', 'slug' => 'unrelated-1', 'is_published' => true]);
        Page::create(['business_id' => $biz->id, 'title' => 'Unrelated 2', 'slug' => 'unrelated-2', 'is_published' => true]);

        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'linksf9.example.com', true);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $page->id,
            commitId: 'commit_test_f9',
            businessName: 'My Biz F9'
        );

        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $links = $xpath->query('//nav[@id="internal-links-x176"]//a');
        $hrefs = [];
        foreach ($links as $link) {
            $hrefs[] = $link->getAttribute('href');
        }

        $this->assertNotContains('/', $hrefs, 'Expected colliding root / to be absent');
        $this->assertContains('/unrelated-1', $hrefs, 'Expected unrelated-1 to be present');
        $this->assertContains('/unrelated-2', $hrefs, 'Expected unrelated-2 to be present');
    }

    public function test_f10_collision_consistency(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Internal Link Tenant F10']);
        Tenancy::set((int) $biz->id);

        $rootPage = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => '/', 'is_published' => true]);

        Page::create(['business_id' => $biz->id, 'title' => 'Services', 'slug' => 'services', 'is_published' => true]);
        Page::create(['business_id' => $biz->id, 'title' => 'Services Slash', 'slug' => '/services', 'is_published' => true]);
        $page = Page::create(['business_id' => $biz->id, 'title' => 'Child', 'slug' => 'services/child', 'is_published' => true]);
        Page::create(['business_id' => $biz->id, 'title' => 'Unrelated 1', 'slug' => 'unrelated-1', 'is_published' => true]);
        Page::create(['business_id' => $biz->id, 'title' => 'Unrelated 2', 'slug' => 'unrelated-2', 'is_published' => true]);

        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'linksf10.example.com', true);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $page->id, // Deploy a page so we can see breadcrumb. Breadcrumb requires pageId that has path.
            commitId: 'commit_test_f10',
            businessName: 'My Biz F10'
        );

        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        $this->assertStringNotContainsString('id="breadcrumb-x176"', $html);

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $links = $xpath->query('//nav[@id="internal-links-x176"]//a');
        $hrefs = [];
        foreach ($links as $link) {
            $hrefs[] = $link->getAttribute('href');
        }

        $this->assertNotContains('/services', $hrefs);
        $this->assertNotContains('/services/child', $hrefs);
    }

    public function test_f11_blank_title_consistency(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Internal Link Tenant F11']);
        Tenancy::set((int) $biz->id);

        Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => '/', 'is_published' => true]);

        Page::create(['business_id' => $biz->id, 'title' => '0', 'slug' => 'zero', 'is_published' => true]);
        $pageZero = Page::create(['business_id' => $biz->id, 'title' => 'Zero Child', 'slug' => 'zero/child', 'is_published' => true]);

        Page::create(['business_id' => $biz->id, 'title' => '   ', 'slug' => 'blank', 'is_published' => true]);
        $pageBlank = Page::create(['business_id' => $biz->id, 'title' => 'Blank Child', 'slug' => 'blank/child', 'is_published' => true]);

        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'linksf11.example.com', true);

        // Deploy zero child
        $resZero = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $pageZero->id,
            commitId: 'commit_test_f11_zero',
            businessName: 'My Biz F11'
        );
        $htmlZero = Storage::disk('local')->get("sites/{$resZero['deploy_hash']}.html");

        $domZero = new \DOMDocument;
        libxml_use_internal_errors(true);
        $domZero->loadHTML($htmlZero);
        $xpathZero = new \DOMXPath($domZero);

        $navLinksZero = [];
        foreach ($xpathZero->query('//nav[@id="internal-links-x176"]//a') as $link) {
            $navLinksZero[] = trim($link->textContent);
        }
        $breadcrumbLinksZero = [];
        foreach ($xpathZero->query('//nav[@id="breadcrumb-x176"]//a') as $link) {
            $breadcrumbLinksZero[] = trim($link->textContent);
        }

        $this->assertContains('0', $navLinksZero, 'Expected 0 in nav');
        $this->assertContains('0', $breadcrumbLinksZero, 'Expected 0 in breadcrumb');

        // Deploy blank child
        $resBlank = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $pageBlank->id,
            commitId: 'commit_test_f11_blank',
            businessName: 'My Biz F11'
        );
        $htmlBlank = Storage::disk('local')->get("sites/{$resBlank['deploy_hash']}.html");

        $domBlank = new \DOMDocument;
        libxml_use_internal_errors(true);
        $domBlank->loadHTML($htmlBlank);
        $xpathBlank = new \DOMXPath($domBlank);

        $navLinksBlank = [];
        foreach ($xpathBlank->query('//nav[@id="internal-links-x176"]//a') as $link) {
            $navLinksBlank[] = trim($link->textContent);
        }
        $breadcrumbLinksBlank = [];
        foreach ($xpathBlank->query('//nav[@id="breadcrumb-x176"]//a') as $link) {
            $breadcrumbLinksBlank[] = trim($link->textContent);
        }

        $this->assertNotContains('   ', $navLinksBlank, 'Expected whitespace to be refused in nav');
        $this->assertNotContains('', $navLinksBlank, 'Expected empty to be refused in nav');

        // Assert child is excluded too per the rule
        $navHrefsBlank = [];
        foreach ($xpathBlank->query('//nav[@id="internal-links-x176"]//a') as $link) {
            $navHrefsBlank[] = $link->getAttribute('href');
        }
        $this->assertNotContains('/blank/child', $navHrefsBlank, 'Descendant of blank should be excluded from nav');

        $this->assertStringNotContainsString('id="breadcrumb-x176"', $htmlBlank, 'Expected breadcrumb to be entirely absent due to excluded ancestor');
    }
}
