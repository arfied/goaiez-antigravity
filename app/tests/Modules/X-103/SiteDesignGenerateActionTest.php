<?php

declare(strict_types=1);

namespace Tests\Modules\X103;

use App\Models\User;
use App\Modules\X103\Actions\SiteDesignGenerateAction;
use App\Modules\X103\Jobs\SiteDesignJob;
use App\Modules\X103\Models\Page;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class SiteDesignGenerateActionTest extends TestCase
{
    use RefreshesTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['credentials.anthropic_api_key' => 'fake-key', 'credentials.openai_api_key' => 'fake-key']);
    }

    private function page(array $blocks): Page
    {
        $owner = User::factory()->create();
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        return Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => $blocks,
            'is_published' => false,
        ]);
    }

    private function fakeAnswer(string $text): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(
                json_encode([
                    'content' => [['type' => 'text', 'text' => $text]],
                    'stop_reason' => 'end_turn',
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
                ]),
                200,
                ['content-type' => 'application/json']
            ),
        ]);
    }

    public function test_a_design_is_cleaned_and_kept_on_the_page(): void
    {
        $page = $this->page([['type' => 'hero', 'headline' => 'Old headline']]);
        $this->fakeAnswer("Here you go:\n```html\n<style>.hero{color:red;background:url(https://evil.test/x.png)}</style><main><section class=\"hero\"><h1>Designed 7301</h1><script>alert(1)</script><a href=\"tel:+15550107302\">Call</a></section></main>\n```");

        $res = app(SiteDesignGenerateAction::class)->handle($page->business_id, $page->id);

        $this->assertSame('ready', $res['status']);
        $page->refresh();
        $design = $page->draft_meta['design'];
        $this->assertSame('ready', $design['status']);
        $this->assertStringContainsString('Designed 7301', $design['html']);
        $this->assertStringContainsString('href="tel:+15550107302"', $design['html']);
        $this->assertStringNotContainsString('<script', $design['html']);
        $this->assertStringNotContainsString('```', $design['html']);
        $this->assertStringContainsString('color:red', $design['style']);
        $this->assertStringNotContainsString('url(', $design['style']);
        $this->assertSame('Old headline', $page->draft_blocks[0]['headline']);
    }

    public function test_a_cut_off_design_is_never_kept(): void
    {
        $page = $this->page([['type' => 'hero', 'headline' => 'H']]);
        $this->fakeAnswer('<style>.a{color:red}</style><main><section><h1>Half a page 7303</h1>');

        $res = app(SiteDesignGenerateAction::class)->handle($page->business_id, $page->id);

        $this->assertSame('failed', $res['status']);
        $this->assertSame('cut_off', $res['reason']);
        $page->refresh();
        $this->assertSame('failed', $page->draft_meta['design']['status']);
        $this->assertArrayNotHasKey('html', $page->draft_meta['design']);
    }

    public function test_the_ai_sees_the_owners_pictures_only_as_tokens(): void
    {
        $page = $this->page([['type' => 'hero', 'headline' => 'H', 'image_path' => 'images/secret-file-7304.jpg', 'image_alt' => 'A painted hallway 7305']]);
        $this->fakeAnswer('<main><img src="[[image:1]]" alt="x"><h1>H</h1></main>');

        app(SiteDesignGenerateAction::class)->handle($page->business_id, $page->id);

        Http::assertSent(fn ($r) => str_contains($r->body(), '[[image:1]]')
            && str_contains($r->body(), 'A painted hallway 7305')
            && ! str_contains($r->body(), 'secret-file-7304'));
        $page->refresh();
        $this->assertSame([1 => 'images/secret-file-7304.jpg'], $page->draft_meta['design']['images']);
    }

    public function test_the_job_gives_the_ai_time_for_a_whole_page(): void
    {
        $page = $this->page([['type' => 'hero', 'headline' => 'H']]);
        $this->fakeAnswer('<main><h1>From the job 7306</h1></main>');

        (new SiteDesignJob($page->business_id, $page->id))->handle(app(SiteDesignGenerateAction::class));

        $this->assertSame(360, config('ai.timeout'));
        $page->refresh();
        $this->assertSame('ready', $page->draft_meta['design']['status']);
        $this->assertStringContainsString('From the job 7306', $page->draft_meta['design']['html']);
    }

    public function test_the_designer_can_have_up_to_three_new_pictures_made(): void
    {
        Storage::fake('local');
        $page = $this->page([['type' => 'hero', 'headline' => 'H']]);
        Http::fake([
            'api.openai.com/v1/images/generations' => Http::response([
                'data' => [['b64_json' => '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=']],
            ], 200, ['Content-Type' => 'application/json']),
        ]);
        $this->fakeAnswer('<main><img src="[[new:a freshly painted living room 7307]]" alt="room"><img src="[[new:two]]" alt="2"><img src="[[new:three]]" alt="3"><img src="[[new:a fourth picture 7308]]" alt="4"><h1>H</h1></main>');

        app(SiteDesignGenerateAction::class)->handle($page->business_id, $page->id);

        $page->refresh();
        $design = $page->draft_meta['design'];
        $this->assertSame('ready', $design['status']);
        $this->assertCount(3, $design['images']);
        $this->assertStringContainsString('src="[[image:1]]"', $design['html']);
        $this->assertStringNotContainsString('[[new:', $design['html']);
        Storage::disk('local')->assertExists($design['images'][1]);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'images/generations') && str_contains($r->body(), 'freshly painted living room 7307'));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'images/generations') && str_contains($r->body(), 'a fourth picture 7308'));
    }
}
