<?php

declare(strict_types=1);

namespace Tests\Modules\X176;

use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVersion;
use App\Modules\X108\Models\Appointment;
use App\Modules\X157\Actions\EdgeDeployAction;
use App\Modules\X157\Actions\EdgeProvisionAction;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SchemaVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_events_correspond(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Visibility Tenant']);
        Tenancy::set((int) $biz->id);

        $page = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home']);
        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'visibility1.example.com', true);

        Appointment::create([
            'business_id' => $biz->id,
            'service_name' => 'First Appointment',
            'start_time' => now()->addDays(1),
            'end_time' => now()->addDays(1)->addHours(1),
        ]);
        Appointment::create([
            'business_id' => $biz->id,
            'service_name' => 'Second Appointment',
            'start_time' => now()->addDays(2),
            'end_time' => now()->addDays(2)->addHours(1),
        ]);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $page->id,
            commitId: 'commit_v1',
            businessName: 'Visibility Biz'
        );

        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $json = json_decode($matches[1], true);

        $schemaEvents = [];
        if (isset($json['event'])) {
            $events = isset($json['event']['@type']) ? [$json['event']] : $json['event'];
            foreach ($events as $event) {
                $schemaEvents[] = $event['name'];
            }
        }

        preg_match('/<div id="events-x176">(.*?)<\/div>\n(?:<div|<nav|<script|<\/body)/s', $html, $blockMatches);
        $visibleEvents = [];
        if (! empty($blockMatches)) {
            preg_match_all('/<div class="event-item" data-name="([^"]+)">/', $blockMatches[1], $itemMatches);
            $visibleEvents = $itemMatches[1];
        }

        $this->assertCount(2, $schemaEvents);
        $this->assertEquals($schemaEvents, $visibleEvents);
    }

    public function test_address_corresponds(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Visibility Tenant Address']);
        $biz->update([
            'address' => [
                'line1' => '123 Test St',
                'city' => 'Testville',
                'region' => 'TS',
                'postal_code' => '12345',
                'country' => 'US',
            ],
        ]);
        Tenancy::set((int) $biz->id);

        $page = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home']);
        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'visibility2.example.com', true);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $page->id,
            commitId: 'commit_v2',
            businessName: 'Visibility Biz'
        );

        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $json = json_decode($matches[1], true);

        $this->assertArrayHasKey('address', $json);
        $claimedAddress = $json['address'];

        preg_match('/<div id="address-x176">(.*?)<\/div>\n(?:<div|<nav|<script|<\/body)/s', $html, $blockMatches);
        $this->assertNotEmpty($blockMatches, 'Visible address block not found');
        $blockHtml = $blockMatches[1];

        $this->assertStringContainsString($claimedAddress['streetAddress'], $blockHtml);
        $this->assertStringContainsString($claimedAddress['addressLocality'], $blockHtml);
        $this->assertStringContainsString($claimedAddress['addressRegion'], $blockHtml);
        $this->assertStringContainsString($claimedAddress['postalCode'], $blockHtml);
        $this->assertStringContainsString($claimedAddress['addressCountry'], $blockHtml);
    }

    public function test_video_corresponds(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Visibility Tenant Video']);
        Tenancy::set((int) $biz->id);

        $page = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home']);
        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'visibility3.example.com', true);

        PageVersion::create([
            'business_id' => $biz->id,
            'page_id' => $page->id,
            'commit_id' => 'commit_v3',
            'content_blocks' => [
                [
                    'type' => 'video_embed',
                    'name' => 'My Test Video',
                    'contentUrl' => 'https://example.com/video.mp4',
                    'uploadDate' => '2026-01-01T00:00:00Z',
                ],
            ],
        ]);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $page->id,
            commitId: 'commit_v3',
            businessName: 'Visibility Biz'
        );

        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $json = json_decode($matches[1], true);

        $schemaVideos = [];
        if (isset($json['video'])) {
            $videos = isset($json['video']['@type']) ? [$json['video']] : $json['video'];
            foreach ($videos as $video) {
                $schemaVideos[] = $video['name'];
            }
        }

        preg_match('/<div id="videos-x176"[^>]*>(.*?)<\/div>\n(?:<div|<nav|<script|<\/body)/s', $html, $blockMatches);
        $visibleVideos = [];
        if (! empty($blockMatches)) {
            preg_match_all('/<div class="video-item" data-name="([^"]+)"/', $blockMatches[1], $itemMatches);
            $visibleVideos = $itemMatches[1];
        }

        $this->assertCount(1, $schemaVideos);
        $this->assertEquals($schemaVideos, $visibleVideos);
    }

    /**
     * [G8-16]
     */
    public function test_faq_corresponds(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Visibility Tenant FAQ']);
        Tenancy::set((int) $biz->id);

        $page = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home']);
        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'visibility-faq.example.com', true);

        PageVersion::create([
            'business_id' => $biz->id,
            'page_id' => $page->id,
            'commit_id' => 'commit_faq',
            'content_blocks' => [
                [
                    'type' => 'faq',
                    'question' => 'What is this?',
                    'answer' => 'It is a test.',
                ],
            ],
        ]);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $page->id,
            commitId: 'commit_faq',
            businessName: 'Visibility Biz FAQ'
        );

        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $json = json_decode($matches[1], true);

        $schemaFaqs = [];
        if (isset($json['mainEntity'])) {
            foreach ($json['mainEntity'] as $q) {
                if (($q['@type'] ?? '') === 'Question') {
                    $schemaFaqs[] = $q['name'];
                }
            }
        }

        preg_match_all('/<div class="faq-item" data-question="([^"]+)"/', $html, $itemMatches);
        $visibleFaqs = $itemMatches[1];

        $this->assertCount(1, $schemaFaqs);
        $this->assertNotEmpty($visibleFaqs);
        $this->assertEquals($schemaFaqs, $visibleFaqs);
    }

    public function test_an_ai_placed_faq_in_items_shape_reaches_the_schema_and_matches_the_page(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Visibility Tenant FAQ Items']);
        Tenancy::set((int) $biz->id);

        $page = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home']);
        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'visibility-faq-items.example.com', true);

        PageVersion::create([
            'business_id' => $biz->id,
            'page_id' => $page->id,
            'commit_id' => 'commit_faq_items',
            'content_blocks' => [
                ['type' => 'faq', 'items' => [['question' => 'Distinctive question 4611?', 'answer' => 'Distinctive answer 4612'], ['question' => 'Distinctive question 4613?', 'answer' => 'Distinctive answer 4614']], 'source' => 'ai', 'model' => 'openai-4o-mini'],
            ],
        ]);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $page->id,
            commitId: 'commit_faq_items',
            businessName: 'Visibility Biz FAQ Items'
        );

        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $json = json_decode($matches[1] ?? '{}', true);

        $schemaFaqs = [];
        if (isset($json['mainEntity'])) {
            foreach ($json['mainEntity'] as $q) {
                if (($q['@type'] ?? '') === 'Question') {
                    $schemaFaqs[] = $q['name'];
                }
            }
        }

        preg_match_all('/<div class="faq-item" data-question="([^"]+)"/', $html, $itemMatches);
        $visibleFaqs = $itemMatches[1];

        $this->assertCount(2, $schemaFaqs);
        $this->assertNotEmpty($visibleFaqs);
        $this->assertEquals($schemaFaqs, $visibleFaqs);
        $this->assertSame('Distinctive question 4611?', $json['mainEntity'][0]['name'] ?? null);
    }

    public function test_f1_video_corresponds_with_hierarchy(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Visibility Tenant Video F1']);
        Tenancy::set((int) $biz->id);

        $page1 = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home', 'is_published' => true]);
        $page2 = Page::create(['business_id' => $biz->id, 'title' => 'Child', 'slug' => 'home/child', 'is_published' => true]);

        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'visibility5.example.com', true);

        PageVersion::create([
            'business_id' => $biz->id,
            'page_id' => $page1->id,
            'commit_id' => 'commit_v3',
            'content_blocks' => [
                [
                    'type' => 'video_embed',
                    'name' => 'My Test Video',
                    'contentUrl' => 'https://example.com/video.mp4',
                    'uploadDate' => '2026-01-01T00:00:00Z',
                ],
            ],
        ]);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $page1->id,
            commitId: 'commit_v3',
            businessName: 'Visibility Biz'
        );

        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $json = json_decode($matches[1], true);

        $schemaVideos = [];
        if (isset($json['video'])) {
            $videos = isset($json['video']['@type']) ? [$json['video']] : $json['video'];
            foreach ($videos as $video) {
                $schemaVideos[] = $video['name'];
            }
        }

        preg_match('/<div id="videos-x176"[^>]*>(.*?)<\/div>\n(?:<div|<nav|<script|<\/body)/s', $html, $blockMatches);
        $visibleVideos = [];
        if (! empty($blockMatches)) {
            preg_match_all('/<div class="video-item" data-name="([^"]+)"/', $blockMatches[1], $itemMatches);
            $visibleVideos = $itemMatches[1];
        }

        $this->assertCount(1, $schemaVideos);
        $this->assertEquals($schemaVideos, $visibleVideos);
    }

    public function test_falsifier_absence(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Visibility Tenant Falsifier']);
        $biz->update(['address' => null]);
        Tenancy::set((int) $biz->id);

        $page = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home']);
        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'visibility4.example.com', true);

        PageVersion::create([
            'business_id' => $biz->id,
            'page_id' => $page->id,
            'commit_id' => 'commit_v4',
            'content_blocks' => [],
        ]);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $page->id,
            commitId: 'commit_v4',
            businessName: 'Visibility Biz'
        );

        $this->assertEquals('deployed', $res['status']);
        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $this->assertNotEmpty($matches, 'JSON-LD script tag should still be present');

        $json = json_decode($matches[1], true);
        $this->assertArrayNotHasKey('event', $json);
        $this->assertArrayNotHasKey('address', $json);
        $this->assertArrayNotHasKey('video', $json);
        $this->assertArrayNotHasKey('mainEntity', $json);

        $this->assertStringNotContainsString('id="events-x176"', $html);
        $this->assertStringNotContainsString('id="address-x176"', $html);
        $this->assertStringNotContainsString('id="videos-x176"', $html);
        $this->assertStringNotContainsString('id="faq-x176"', $html);
    }

    public function test_f2_breadcrumb_corresponds(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Visibility Tenant Breadcrumb F2']);
        Tenancy::set((int) $biz->id);

        $page1 = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home', 'is_published' => true]);
        $page2 = Page::create(['business_id' => $biz->id, 'title' => 'Child', 'slug' => 'home/child', 'is_published' => true]);

        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'visibility-f2.example.com', true);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $page2->id,
            commitId: 'commit_v2',
            businessName: 'Visibility Biz'
        );

        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $json = json_decode($matches[1], true);

        $this->assertArrayHasKey('breadcrumb', $json);
        $schemaBreadcrumbs = array_map(function ($item) {
            return [
                'name' => $item['name'],
                'path' => parse_url($item['item'], PHP_URL_PATH),
            ];
        }, $json['breadcrumb']['itemListElement']);

        preg_match('/<nav id="breadcrumb-x176">(.*?)<\/nav>/s', $html, $blockMatches);
        $this->assertNotEmpty($blockMatches, 'Visible breadcrumb nav not found');

        preg_match_all('/<a href="([^"]+)">([^<]+)<\/a>/', $blockMatches[1], $linkMatches, PREG_SET_ORDER);

        $visibleBreadcrumbs = array_map(function ($match) {
            return [
                'name' => $match[2],
                'path' => $match[1],
            ];
        }, $linkMatches);

        $this->assertEquals($schemaBreadcrumbs, $visibleBreadcrumbs);
    }

    public function test_f3_breadcrumb_no_hierarchy(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Visibility Tenant Breadcrumb F3']);
        Tenancy::set((int) $biz->id);

        $page1 = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home', 'is_published' => true]);

        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'visibility-f3.example.com', true);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $page1->id,
            commitId: 'commit_v3',
            businessName: 'Visibility Biz'
        );

        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $json = json_decode($matches[1], true);

        $this->assertArrayNotHasKey('breadcrumb', $json);
        $this->assertStringNotContainsString('id="breadcrumb-x176"', $html);
    }

    public function test_f4_breadcrumb_unpublished_ancestor(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'Visibility Tenant Breadcrumb F4']);
        Tenancy::set((int) $biz->id);

        $page1 = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home', 'is_published' => false]);
        $page2 = Page::create(['business_id' => $biz->id, 'title' => 'Child', 'slug' => 'home/child', 'is_published' => true]);

        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'visibility-f4.example.com', true);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $page2->id,
            commitId: 'commit_v4',
            businessName: 'Visibility Biz'
        );

        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $json = json_decode($matches[1], true);

        $this->assertArrayNotHasKey('breadcrumb', $json);
        $this->assertStringNotContainsString('id="breadcrumb-x176"', $html);
    }

    public function test_f5_breadcrumb_ancestry_query_bounded()
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'F5 Biz']);
        Tenancy::set((int) $biz->id);
        $businessId = $biz->id;
        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'f5.example.com', true);

        Page::create(['business_id' => $businessId, 'title' => 'Home', 'slug' => '/', 'is_published' => true]);
        Page::create(['business_id' => $businessId, 'title' => 'Parent', 'slug' => '/parent', 'is_published' => true]);
        $page = Page::create(['business_id' => $businessId, 'title' => 'Child', 'slug' => 'parent/child', 'is_published' => true]);
        Page::create(['business_id' => $businessId, 'title' => 'Unrelated', 'slug' => 'unrelated', 'is_published' => true]);

        $commitId = 'commit-f5';

        DB::enableQueryLog();

        $action = app(EdgeDeployAction::class);
        $action->handle($businessId, $zone->id, pageId: $page->id, commitId: $commitId, businessName: 'F5 Biz');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $ancestryQuery = null;
        $candidateCount = 0;
        foreach ($queries as $q) {
            if (str_contains($q['query'], 'from "pages" where "business_id" = ? and "is_published" = ?')) {
                $hasPaths = false;
                $hasParent = false;
                $hasChild = false;
                foreach ($q['bindings'] as $binding) {
                    if ($binding === 'parent' || $binding === '/parent') {
                        $hasParent = true;
                    }
                    if ($binding === 'parent/child' || $binding === '/parent/child') {
                        $hasChild = true;
                    }
                }

                if ($hasParent && $hasChild) {
                    $ancestryQuery = $q;
                    $candidateCount++;
                }
            }
        }

        $this->assertEquals(1, $candidateCount, 'Expected exactly one ancestry query containing the ancestor paths');
        $this->assertNotNull($ancestryQuery);

        $hasUnrelated = false;
        foreach ($ancestryQuery['bindings'] as $binding) {
            if ($binding === 'unrelated' || $binding === '/unrelated') {
                $hasUnrelated = true;
            }
        }
        $this->assertFalse($hasUnrelated, 'Query should not contain unrelated in bindings');
    }

    public function test_f6_breadcrumb_collision_refuses_trail()
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'F6 Biz']);
        Tenancy::set((int) $biz->id);
        $businessId = $biz->id;
        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'f6.example.com', true);

        Page::create(['business_id' => $businessId, 'title' => 'Services', 'slug' => 'services', 'is_published' => true]);
        Page::create(['business_id' => $businessId, 'title' => 'Services Slash', 'slug' => '/services', 'is_published' => true]);
        $page = Page::create(['business_id' => $businessId, 'title' => 'Child', 'slug' => 'services/child', 'is_published' => true]);

        $commitId = 'commit-f6';

        $action = app(EdgeDeployAction::class);
        $res = $action->handle($businessId, $zone->id, pageId: $page->id, commitId: $commitId, businessName: 'F6 Biz');

        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        $this->assertStringNotContainsString('id="breadcrumb-x176"', $html);
    }

    public function test_f7_breadcrumb_resolves_a_trailing_slash_ancestor()
    {
        Storage::fake('local');
        $biz = self::provisionTenant(['name' => 'F7 Biz']);
        Tenancy::set((int) $biz->id);
        $businessId = $biz->id;
        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'f7.example.com', true);

        Page::create(['business_id' => $businessId, 'title' => 'Services Slash Parent', 'slug' => '/services/', 'is_published' => true]);
        $page = Page::create(['business_id' => $businessId, 'title' => 'Child', 'slug' => 'services/child', 'is_published' => true]);

        $commitId = 'commit-f7';

        $action = app(EdgeDeployAction::class);
        $res = $action->handle($businessId, $zone->id, pageId: $page->id, commitId: $commitId, businessName: 'F7 Biz');

        $html = Storage::disk('local')->get("sites/{$res['deploy_hash']}.html");

        $this->assertStringContainsString('id="breadcrumb-x176"', $html);
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $links = $xpath->query('//nav[@id="breadcrumb-x176"]//a');
        $texts = [];
        foreach ($links as $link) {
            $texts[] = $link->textContent;
        }
        $this->assertContains('Services Slash Parent', $texts);

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $json = json_decode($matches[1], true);
        $this->assertArrayHasKey('breadcrumb', $json);
    }
}
