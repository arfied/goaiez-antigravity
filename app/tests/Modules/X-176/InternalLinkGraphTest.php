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
}
