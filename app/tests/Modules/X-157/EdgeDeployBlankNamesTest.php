<?php

declare(strict_types=1);

namespace Tests\Modules\X157;

use App\Models\Business;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVersion;
use App\Modules\X108\Models\Appointment;
use App\Modules\X157\Actions\EdgeDeployAction;
use App\Modules\X157\Actions\EdgeProvisionAction;
use App\Modules\X163\Models\PriceBookItem;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class EdgeDeployBlankNamesTest extends TestCase
{
    use RefreshesTenantDatabase;

    private EdgeProvisionAction $provisionAction;

    private EdgeDeployAction $deployAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provisionAction = new EdgeProvisionAction;
        $this->deployAction = app(EdgeDeployAction::class);
        Storage::fake('local');
    }

    public function test_event_deploy_skips_blank_name()
    {
        $biz = Business::factory()->create();
        $zone = $this->provisionAction->handle($biz->id, 'blank-event.com', true);

        $page = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home', 'is_published' => true]);
        $commitId = 'commit_event';

        Appointment::create([
            'business_id' => $biz->id,
            'service_name' => '',
            'start_time' => now()->addDays(1),
            'end_time' => now()->addDays(1)->addHours(1),
        ]);
        Appointment::create([
            'business_id' => $biz->id,
            'service_name' => 'Valid Event',
            'start_time' => now()->addDays(2),
            'end_time' => now()->addDays(2)->addHours(1),
        ]);

        $result = $this->deployAction->handle($biz->id, $zone->id, 100, 1500, $page->id, $commitId, 'Biz Name');
        $this->assertEquals('deployed', $result['status']);

        $html = Storage::disk('local')->get("sites/{$result['deploy_hash']}.html");
        $this->assertStringContainsString('application/ld+json', $html);

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $json = json_decode($matches[1], true);

        $this->assertArrayHasKey('event', $json);
        $this->assertCount(1, $json['event']);
        $this->assertEquals('Valid Event', $json['event'][0]['name']);
    }

    public function test_offer_deploy_skips_blank_name()
    {
        $biz = Business::factory()->create();
        $zone = $this->provisionAction->handle($biz->id, 'blank-offer.com', true);

        $page = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home', 'is_published' => true]);
        $commitId = 'commit_offer';

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => '',
            'price_cents' => 1000,
            'is_confirmed' => true,
            'is_sample' => false,
        ]);
        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Valid Offer',
            'price_cents' => 2000,
            'is_confirmed' => true,
            'is_sample' => false,
        ]);

        $result = $this->deployAction->handle($biz->id, $zone->id, 100, 1500, $page->id, $commitId, 'Biz Name');
        $this->assertEquals('deployed', $result['status']);

        $html = Storage::disk('local')->get("sites/{$result['deploy_hash']}.html");
        $this->assertStringContainsString('application/ld+json', $html);
        $this->assertStringNotContainsString('"name":""', $html);

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $json = json_decode($matches[1], true);

        $this->assertArrayHasKey('hasOfferCatalog', $json);
        $this->assertCount(1, $json['hasOfferCatalog']['itemListElement']);
        $this->assertEquals('Valid Offer', $json['hasOfferCatalog']['itemListElement'][0]['itemOffered']['name']);
    }

    public function test_video_deploy_skips_blank_name()
    {
        $biz = Business::factory()->create();
        $zone = $this->provisionAction->handle($biz->id, 'blank-video.com', true);

        $page = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home', 'is_published' => true]);
        $commitId = 'commit_video';

        PageVersion::create([
            'business_id' => $biz->id,
            'page_id' => $page->id,
            'commit_id' => $commitId,
            'content_blocks' => [
                [
                    'type' => 'video_embed',
                    'name' => '   ',
                    'contentUrl' => 'https://video.com',
                    'uploadDate' => '2026-09-07T00:00:00Z',
                ],
                [
                    'type' => 'video_embed',
                    'name' => 'Valid Video',
                    'contentUrl' => 'https://video.com/valid',
                    'uploadDate' => '2026-09-07T00:00:00Z',
                ],
            ],
            'ssl_installed' => true,
        ]);

        $result = $this->deployAction->handle($biz->id, $zone->id, 100, 1500, $page->id, $commitId, 'Biz Name');
        $this->assertEquals('deployed', $result['status']);

        $html = Storage::disk('local')->get("sites/{$result['deploy_hash']}.html");
        $this->assertStringContainsString('application/ld+json', $html);

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $json = json_decode($matches[1], true);

        $this->assertArrayHasKey('video', $json);
        $this->assertCount(1, $json['video']);
        $this->assertEquals('Valid Video', $json['video'][0]['name']);
    }

    public function test_faq_deploy_skips_blank_question()
    {
        $biz = Business::factory()->create();
        $zone = $this->provisionAction->handle($biz->id, 'blank-faq.com', true);

        $page = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home', 'is_published' => true]);
        $commitId = 'commit_faq';

        PageVersion::create([
            'business_id' => $biz->id,
            'page_id' => $page->id,
            'commit_id' => $commitId,
            'content_blocks' => [
                [
                    'type' => 'faq',
                    'question' => '  ',
                    'answer' => 'Answer text',
                ],
                [
                    'type' => 'faq',
                    'question' => 'Valid Question',
                    'answer' => 'Valid Answer',
                ],
            ],
            'ssl_installed' => true,
        ]);

        $result = $this->deployAction->handle($biz->id, $zone->id, 100, 1500, $page->id, $commitId, 'Biz Name');
        $this->assertEquals('deployed', $result['status']);

        $html = Storage::disk('local')->get("sites/{$result['deploy_hash']}.html");
        $this->assertStringContainsString('application/ld+json', $html);

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $json = json_decode($matches[1], true);

        $this->assertArrayHasKey('mainEntity', $json);
        $this->assertCount(1, $json['mainEntity']);
        $this->assertEquals('Valid Question', $json['mainEntity'][0]['name']);
    }

    public function test_a_faq_block_whose_question_is_not_a_scalar_is_excluded_and_the_deploy_survives()
    {
        $biz = Business::factory()->create();
        $zone = $this->provisionAction->handle($biz->id, 'blank-faq.com', true);

        $page = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home', 'is_published' => true]);
        $commitId = 'commit_faq';

        PageVersion::create([
            'business_id' => $biz->id,
            'page_id' => $page->id,
            'commit_id' => $commitId,
            'content_blocks' => [
                [
                    'type' => 'faq',
                    'question' => ['nested' => 'value'],
                    'answer' => 'Answer text',
                ],
                [
                    'type' => 'faq',
                    'question' => 'Valid Question',
                    'answer' => 'Valid Answer',
                ],
            ],
            'ssl_installed' => true,
        ]);

        $result = $this->deployAction->handle($biz->id, $zone->id, 100, 1500, $page->id, $commitId, 'Biz Name');
        $this->assertEquals('deployed', $result['status']);

        $html = Storage::disk('local')->get("sites/{$result['deploy_hash']}.html");
        $this->assertStringContainsString('application/ld+json', $html);

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $json = json_decode($matches[1], true);

        $this->assertArrayHasKey('mainEntity', $json);
        $this->assertCount(1, $json['mainEntity']);
        $this->assertEquals('Valid Question', $json['mainEntity'][0]['name']);
    }

    public function test_a_video_block_whose_name_is_not_a_scalar_is_excluded_and_the_deploy_survives()
    {
        $biz = Business::factory()->create();
        $zone = $this->provisionAction->handle($biz->id, 'blank-video.com', true);

        $page = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home', 'is_published' => true]);
        $commitId = 'commit_video';

        PageVersion::create([
            'business_id' => $biz->id,
            'page_id' => $page->id,
            'commit_id' => $commitId,
            'content_blocks' => [
                [
                    'type' => 'video_embed',
                    'name' => ['nested' => 'value'],
                    'contentUrl' => 'https://video.com',
                    'uploadDate' => '2026-09-07T00:00:00Z',
                ],
                [
                    'type' => 'video_embed',
                    'name' => 'Valid Video',
                    'contentUrl' => 'https://video.com/valid',
                    'uploadDate' => '2026-09-07T00:00:00Z',
                ],
            ],
            'ssl_installed' => true,
        ]);

        $result = $this->deployAction->handle($biz->id, $zone->id, 100, 1500, $page->id, $commitId, 'Biz Name');
        $this->assertEquals('deployed', $result['status']);

        $html = Storage::disk('local')->get("sites/{$result['deploy_hash']}.html");
        $this->assertStringContainsString('application/ld+json', $html);

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $json = json_decode($matches[1], true);

        $this->assertArrayHasKey('video', $json);
        $this->assertCount(1, $json['video']);
        $this->assertEquals('Valid Video', $json['video'][0]['name']);
    }
}
