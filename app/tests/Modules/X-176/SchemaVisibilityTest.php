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

        preg_match('/<div id="events-x176">(.*?)<\/div>\n(?:<div|<script|<\/body)/s', $html, $blockMatches);
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

        preg_match('/<div id="address-x176">(.*?)<\/div>\n(?:<div|<script|<\/body)/s', $html, $blockMatches);
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

        preg_match('/<div id="videos-x176">(.*?)<\/div>\n(?:<div|<script|<\/body)/s', $html, $blockMatches);
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

        $this->assertStringNotContainsString('id="events-x176"', $html);
        $this->assertStringNotContainsString('id="address-x176"', $html);
        $this->assertStringNotContainsString('id="videos-x176"', $html);
    }
}
