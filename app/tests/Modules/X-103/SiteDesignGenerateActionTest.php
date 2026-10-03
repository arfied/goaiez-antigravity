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

    /**
     * @return array<string, mixed>
     */
    private function claudeBody(string $text): array
    {
        return [
            'content' => [['type' => 'text', 'text' => $text]],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
        ];
    }

    private function fakeAnswer(string $text): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->claudeBody($text), 200, ['content-type' => 'application/json'])]);
    }

    public function test_a_design_is_a_json_page_cleaned_by_the_same_rules_as_every_ai_edit(): void
    {
        $page = $this->page([['type' => 'hero', 'headline' => 'Old headline']]);
        $this->fakeAnswer("Here is the page:\n```json\n".json_encode([
            'theme' => 'bold-trade',
            'style' => ['palette' => ['primary' => '#c2410c']],
            'blocks' => [
                ['type' => 'hero', 'headline' => 'Designed 7301', 'subline' => 'S', 'variant' => 'centered', 'cta_label' => 'Call', 'cta_url' => 'tel:+15550107302', 'image_path' => 'x-7300.jpg'],
                ['type' => 'script', 'text' => 'bad'],
                ['type' => 'services', 'heading' => 'What we do', 'items' => [['name' => 'Painting 7303', 'price_text' => 'from 100', 'image_path' => 'y-7300.jpg']]],
                ['type' => 'contact', 'phone' => '0100'],
                ['type' => 'cta_band', 'heading' => 'Call us 7304', 'label' => 'Go', 'url' => 'javascript:alert(1)'],
            ],
            'explanation' => 'Bold and simple.',
        ])."\n```");

        $res = app(SiteDesignGenerateAction::class)->handle($page->business_id, $page->id);

        $this->assertSame('ready', $res['status']);
        $page->refresh();
        $design = $page->draft_meta['designs']['claude'];
        $this->assertSame('bold-trade', $design['theme']);
        $this->assertSame('#c2410c', $design['style']['palette']['primary']);
        $this->assertSame(['hero', 'services', 'contact', 'cta_band'], array_column($design['blocks'], 'type'));
        $this->assertSame('centered', $design['blocks'][0]['variant']);
        $this->assertSame('tel:+15550107302', $design['blocks'][0]['cta_url']);
        $json = json_encode($design['blocks']);
        foreach (['x-7300.jpg', 'y-7300.jpg', 'javascript:'] as $needle) {
            $this->assertStringNotContainsString($needle, $json, $needle);
        }
        $this->assertSame('Bold and simple.', $design['explanation']);
        $this->assertEquals([['type' => 'hero', 'headline' => 'Old headline']], $page->draft_blocks);
    }

    public function test_an_answer_that_is_not_json_or_has_no_usable_section_is_never_kept(): void
    {
        $page = $this->page([['type' => 'hero', 'headline' => 'H']]);
        Http::fake(['api.anthropic.com/*' => Http::sequence()
            ->push($this->claudeBody('Sorry, I cannot help with that.'))
            ->push($this->claudeBody('{"theme":"bold-trade","blocks":[{"type":"script","text":"x"}]}')),
        ]);

        $first = app(SiteDesignGenerateAction::class)->handle($page->business_id, $page->id);
        $this->assertSame('not_json', $first['reason']);

        $second = app(SiteDesignGenerateAction::class)->handle($page->business_id, $page->id);
        $this->assertSame('no_valid_blocks', $second['reason']);

        $page->refresh();
        $this->assertSame('failed', $page->draft_meta['designs']['claude']['status']);
        $this->assertArrayNotHasKey('blocks', $page->draft_meta['designs']['claude']);
    }

    public function test_the_ai_is_given_the_themes_and_the_owners_picture_stays_on_the_hero(): void
    {
        $page = $this->page([['type' => 'hero', 'headline' => 'H', 'image_path' => 'images/secret-file-7304.jpg', 'image_alt' => 'A painted hallway 7305']]);
        $this->fakeAnswer(json_encode(['theme' => 'warm-local', 'blocks' => [['type' => 'hero', 'headline' => 'New 7306']]]));

        app(SiteDesignGenerateAction::class)->handle($page->business_id, $page->id);

        Http::assertSent(fn ($r) => str_contains($r->body(), 'warm-local')
            && str_contains($r->body(), 'Section types and their fields')
            && ! str_contains($r->body(), 'secret-file-7304'));
        $page->refresh();
        $hero = $page->draft_meta['designs']['claude']['blocks'][0];
        $this->assertSame('New 7306', $hero['headline']);
        $this->assertSame('images/secret-file-7304.jpg', $hero['image_path']);
        $this->assertSame('A painted hallway 7305', $hero['image_alt']);
    }

    public function test_the_job_gives_the_ai_time_for_a_whole_page(): void
    {
        $page = $this->page([['type' => 'hero', 'headline' => 'H']]);
        $this->fakeAnswer(json_encode(['theme' => 'clean-clinic', 'blocks' => [['type' => 'hero', 'headline' => 'From the job 7307']]]));

        (new SiteDesignJob($page->business_id, $page->id, 'claude'))->handle(app(SiteDesignGenerateAction::class));

        $this->assertSame(360, config('ai.timeout'));
        $page->refresh();
        $this->assertSame('ready', $page->draft_meta['designs']['claude']['status']);
        $this->assertSame('From the job 7307', $page->draft_meta['designs']['claude']['blocks'][0]['headline']);
    }

    public function test_the_designer_can_have_a_hero_picture_made_when_there_is_none(): void
    {
        Storage::fake('local');
        $page = $this->page([['type' => 'hero', 'headline' => 'H']]);
        Http::fake([
            'api.openai.com/v1/images/generations' => Http::response([
                'data' => [['b64_json' => '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=']],
            ], 200, ['Content-Type' => 'application/json']),
        ]);
        $this->fakeAnswer(json_encode([
            'theme' => 'fresh-friendly',
            'blocks' => [['type' => 'hero', 'headline' => 'H2']],
            'images' => [['block_index' => 0, 'description' => 'a freshly painted living room 7308']],
        ]));

        app(SiteDesignGenerateAction::class)->handle($page->business_id, $page->id);

        $page->refresh();
        $hero = $page->draft_meta['designs']['claude']['blocks'][0];
        $this->assertNotEmpty($hero['image_path']);
        Storage::disk('local')->assertExists($hero['image_path']);
        $this->assertSame('a freshly painted living room 7308', $hero['image_alt']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'images/generations') && str_contains($r->body(), 'freshly painted living room 7308'));
    }

    public function test_each_ai_designs_on_its_own_model_and_keeps_its_own_design(): void
    {
        $page = $this->page([['type' => 'hero', 'headline' => 'H']]);
        $chat = fn (string $marker) => Http::response([
            'id' => 'x',
            'choices' => [['message' => ['content' => json_encode(['theme' => 'modern-dark', 'blocks' => [['type' => 'hero', 'headline' => $marker]]])]]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
        ], 200, ['Content-Type' => 'application/json']);
        Http::fake([
            'api.openai.com/v1/chat/completions' => $chat('By ChatGPT 7701'),
            'generativelanguage.googleapis.com/*' => $chat('By Gemini 7702'),
            'api.x.ai/*' => $chat('By Grok 7703'),
        ]);
        $this->fakeAnswer(json_encode(['theme' => 'classic-pro', 'blocks' => [['type' => 'hero', 'headline' => 'By Claude 7704']]]));

        foreach (['chatgpt', 'gemini', 'grok', 'claude'] as $engine) {
            $this->assertSame('ready', app(SiteDesignGenerateAction::class)->handle($page->business_id, $page->id, $engine)['status'], $engine);
        }

        $page->refresh();
        $designs = $page->draft_meta['designs'];
        $this->assertSame('By ChatGPT 7701', $designs['chatgpt']['blocks'][0]['headline']);
        $this->assertSame('By Gemini 7702', $designs['gemini']['blocks'][0]['headline']);
        $this->assertSame('By Grok 7703', $designs['grok']['blocks'][0]['headline']);
        $this->assertSame('By Claude 7704', $designs['claude']['blocks'][0]['headline']);
        $this->assertSame('google-gemini-3.8-flash', $designs['gemini']['model']);
        $this->assertSame('anthropic-haiku-4-5', $designs['claude']['model']);
        $this->assertSame('classic-pro', $designs['claude']['theme']);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'generativelanguage.googleapis.com/v1beta/openai/chat/completions')
            && $r['model'] === 'gemini-3.8-flash'
            && isset($r['max_tokens'])
            && $r->hasHeader('Authorization', 'Bearer fake-key'));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.openai.com/v1/chat/completions') && $r['model'] === 'gpt-6-luna');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.x.ai') && $r['model'] === 'grok-4.3');

        $this->assertSame('unknown_engine', app(SiteDesignGenerateAction::class)->handle($page->business_id, $page->id, 'nope')['reason']);
    }
}
