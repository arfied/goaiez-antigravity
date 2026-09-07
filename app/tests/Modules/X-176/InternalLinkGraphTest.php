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

    public function test_falsifier_ancestor_index_is_built_from_full_published_set(): void
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
}
