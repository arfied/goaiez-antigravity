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

        preg_match_all('/<a href="([^"]+)">([^<]+)<\/a>/', $html, $matches);
        $hrefs = $matches[1];

        $this->assertEqualsCanonicalizing([
            '/services',
            '/services/plumbing',
            '/services/plumbing/emergency'
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

        preg_match_all('/<a href="([^"]+)">([^<]+)<\/a>/', $html, $matches);
        $hrefs = $matches[1];

        $this->assertContains('/', $hrefs);

        $edges = [];
        $nodes = $hrefs;

        foreach ($nodes as $node) {
            if ($node === '/') {
                continue;
            }
            $parts = explode('/', trim($node, '/'));
            array_pop($parts);
            $parent = '/' . implode('/', $parts);
            if ($parent === '/') {
                // edge case where parent is root, explode gives empty string but implode is empty
            }
            $edges[] = ['from' => $parent, 'to' => $node];
        }

        $parentsByChild = [];
        foreach ($edges as $edge) {
            if (!isset($parentsByChild[$edge['to']])) {
                $parentsByChild[$edge['to']] = [];
            }
            $parentsByChild[$edge['to']][] = $edge['from'];
        }

        foreach ($nodes as $node) {
            if ($node === '/') {
                $this->assertArrayNotHasKey($node, $parentsByChild, "Root should have no parents");
            } else {
                $this->assertCount(1, $parentsByChild[$node], "Node $node should have exactly one parent");
                $this->assertContains($parentsByChild[$node][0], $nodes, "Parent of $node must be a valid node");
            }
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
                ['type' => 'pixel_script']
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

        preg_match_all('/<a href="([^"]+)">([^<]+)<\/a>/', $html, $matches);
        $hrefs = $matches[1];

        $this->assertContains('/about', $hrefs);
        $this->assertNotContains('/a/b/c', $hrefs);
    }
}
