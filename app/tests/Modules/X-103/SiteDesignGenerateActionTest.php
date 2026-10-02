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
        config(['credentials.anthropic_api_key' => 'fake-key', 'credentials.openai_api_key' => 'fake-key', 'credentials.xai_api_key' => 'fake-key', 'credentials.gemini_api_key' => 'fake-key']);
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
        $design = $page->draft_meta['designs']['claude'];
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
        $this->assertSame('failed', $page->draft_meta['designs']['claude']['status']);
        $this->assertArrayNotHasKey('html', $page->draft_meta['designs']['claude']);
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
        $this->assertSame([1 => 'images/secret-file-7304.jpg'], $page->draft_meta['designs']['claude']['images']);
    }

    public function test_the_job_gives_the_ai_time_for_a_whole_page(): void
    {
        $page = $this->page([['type' => 'hero', 'headline' => 'H']]);
        $this->fakeAnswer('<main><h1>From the job 7306</h1></main>');

        (new SiteDesignJob($page->business_id, $page->id, 'claude'))->handle(app(SiteDesignGenerateAction::class));

        $this->assertSame(360, config('ai.timeout'));
        $page->refresh();
        $this->assertSame('ready', $page->draft_meta['designs']['claude']['status']);
        $this->assertStringContainsString('From the job 7306', $page->draft_meta['designs']['claude']['html']);
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
        $design = $page->draft_meta['designs']['claude'];
        $this->assertSame('ready', $design['status']);
        $this->assertCount(3, $design['images']);
        $this->assertStringContainsString('src="[[image:1]]"', $design['html']);
        $this->assertStringNotContainsString('[[new:', $design['html']);
        Storage::disk('local')->assertExists($design['images'][1]);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'images/generations') && str_contains($r->body(), 'freshly painted living room 7307'));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'images/generations') && str_contains($r->body(), 'a fourth picture 7308'));
    }

    public function test_each_ai_designs_on_its_own_model_and_keeps_its_own_design(): void
    {
        $page = $this->page([['type' => 'hero', 'headline' => 'H']]);
        $chat = fn (string $marker) => Http::response([
            'id' => 'x',
            'choices' => [['message' => ['content' => '<main><h1>'.$marker.'</h1></main>']]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
        ], 200, ['Content-Type' => 'application/json']);
        Http::fake([
            'api.openai.com/v1/chat/completions' => $chat('By ChatGPT 7701'),
            'generativelanguage.googleapis.com/*' => $chat('By Gemini 7702'),
            'api.x.ai/*' => $chat('By Grok 7703'),
        ]);
        $this->fakeAnswer('<main><h1>By Claude 7704</h1></main>');

        foreach (['chatgpt', 'gemini', 'grok', 'claude'] as $engine) {
            $this->assertSame('ready', app(SiteDesignGenerateAction::class)->handle($page->business_id, $page->id, $engine)['status'], $engine);
        }

        $page->refresh();
        $designs = $page->draft_meta['designs'];
        $this->assertStringContainsString('By ChatGPT 7701', $designs['chatgpt']['html']);
        $this->assertStringContainsString('By Gemini 7702', $designs['gemini']['html']);
        $this->assertStringContainsString('By Grok 7703', $designs['grok']['html']);
        $this->assertStringContainsString('By Claude 7704', $designs['claude']['html']);
        $this->assertSame('google-gemini-3.1-pro', $designs['gemini']['model']);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'generativelanguage.googleapis.com/v1beta/openai/chat/completions')
            && $r['model'] === 'gemini-3.1-pro-preview'
            && isset($r['max_tokens'])
            && $r->hasHeader('Authorization', 'Bearer fake-key'));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.openai.com/v1/chat/completions') && $r['model'] === 'gpt-6.1-sol');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.x.ai') && $r['model'] === 'grok-4.7');

        $this->assertSame('unknown_engine', app(SiteDesignGenerateAction::class)->handle($page->business_id, $page->id, 'nope')['reason']);
    }
}
