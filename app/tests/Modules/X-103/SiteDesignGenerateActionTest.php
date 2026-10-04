<?php

declare(strict_types=1);

namespace Tests\Modules\X103;

use App\Enums\AiModel;
use App\Models\Business;
use App\Models\Location;
use App\Models\User;
use App\Modules\X103\Actions\SiteDesignGenerateAction;
use App\Modules\X103\Actions\SiteDesignUseAction;
use App\Modules\X103\Actions\SiteEditApplyAction;
use App\Modules\X103\Actions\SiteTemplateApplyAction;
use App\Modules\X103\Domain\PagePreview;
use App\Modules\X103\Domain\SiteDesignEngines;
use App\Modules\X103\Jobs\SiteDesignJob;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\SiteInventoryImage;
use App\Modules\X103\Models\SiteInventoryPage;
use App\Services\Config\DefaultsRegistry;
use App\Services\Industry\IndustryStartingPoints;
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
        // The second AI (GPT-6 Luna) answers unusably too, so each call fails with the FIRST AI's reason.
        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push($this->claudeBody('Sorry, I cannot help with that.'))
                ->push($this->claudeBody('{"theme":"bold-trade","blocks":[{"type":"script","text":"x"}]}')),
            'api.openai.com/v1/chat/completions' => Http::response([
                'id' => 'x',
                'choices' => [['message' => ['content' => 'Sorry, no.']]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ], 200, ['Content-Type' => 'application/json']),
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

    public function test_the_designer_can_order_up_to_three_pictures_for_a_hero_an_about_and_a_call_to_action(): void
    {
        Storage::fake('local');
        $page = $this->page([['type' => 'hero', 'headline' => 'H']]);
        Http::fake([
            'api.openai.com/v1/images/generations' => Http::response([
                'data' => [['b64_json' => '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=']],
            ], 200, ['Content-Type' => 'application/json']),
        ]);
        $this->fakeAnswer(json_encode([
            'theme' => 'bold-trade',
            'blocks' => [
                ['type' => 'hero', 'headline' => 'H'],
                ['type' => 'services', 'items' => [['name' => 'S']]],
                ['type' => 'about', 'text' => 'A'],
                ['type' => 'cta_band', 'heading' => 'C'],
                ['type' => 'about', 'text' => 'A2'],
            ],
            'images' => [
                ['block_index' => 1, 'description' => 'not for services 7911'],
                ['block_index' => 0, 'description' => 'hero photo 7912'],
                ['block_index' => 2, 'description' => 'about photo 7913'],
                ['block_index' => 3, 'description' => 'cta photo 7914'],
                ['block_index' => 4, 'description' => 'a fourth photo 7915'],
            ],
        ]));

        app(SiteDesignGenerateAction::class)->handle($page->business_id, $page->id);

        $page->refresh();
        $blocks = $page->draft_meta['designs']['claude']['blocks'];
        $this->assertArrayNotHasKey('image_path', $blocks[1]);
        $this->assertNotEmpty($blocks[0]['image_path']);
        $this->assertNotEmpty($blocks[2]['image_path']);
        $this->assertNotEmpty($blocks[3]['image_path']);
        $this->assertArrayNotHasKey('image_path', $blocks[4]);
        $made = collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]->url(), 'images/generations'))->count();
        $this->assertSame(3, $made);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'images/generations') && str_contains($r->body(), 'not for services 7911'));
    }

    public function test_an_unusable_answer_is_retried_once_on_a_second_ai(): void
    {
        $page = $this->page([['type' => 'hero', 'headline' => 'H']]);
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'id' => 'x',
                'choices' => [['message' => ['content' => json_encode(['theme' => 'clean-clinic', 'blocks' => [['type' => 'hero', 'headline' => 'Rescued 8201']]])]]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ], 200, ['Content-Type' => 'application/json']),
        ]);
        $this->fakeAnswer('Sorry, I cannot help with that.');

        $res = app(SiteDesignGenerateAction::class)->handle($page->business_id, $page->id, 'claude');

        $this->assertSame('ready', $res['status']);
        $page->refresh();
        $design = $page->draft_meta['designs']['claude'];
        $this->assertSame('Rescued 8201', $design['blocks'][0]['headline']);
        $this->assertSame('openai-gpt-6-luna', $design['model']);
        $this->assertSame('anthropic-haiku-4-5', $design['fallback_from']);
    }

    public function test_a_designer_model_is_an_admin_setting(): void
    {
        $page = $this->page([['type' => 'hero', 'headline' => 'H']]);
        app(DefaultsRegistry::class)->set('sites.design.engine.claude', AiModel::ClaudeSonnet5->value, 'test_user');
        $this->fakeAnswer(json_encode(['theme' => 'bold-trade', 'blocks' => [['type' => 'hero', 'headline' => 'From Sonnet 8202']]]));

        app(SiteDesignGenerateAction::class)->handle($page->business_id, $page->id, 'claude');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.anthropic.com') && $r['model'] === AiModel::ClaudeSonnet5->apiModelId());
        $page->refresh();
        $this->assertSame(AiModel::ClaudeSonnet5->value, $page->draft_meta['designs']['claude']['model']);
    }

    public function test_a_designer_setting_that_names_a_picture_model_falls_back_to_its_default(): void
    {
        $this->page([['type' => 'hero', 'headline' => 'H']]);
        app(DefaultsRegistry::class)->set('sites.design.engine.claude', AiModel::FluxSchnell->value, 'test_user');

        $this->assertSame(AiModel::ClaudeHaiku45, SiteDesignEngines::model('claude'));
        $this->assertSame(AiModel::Gpt6Luna, SiteDesignEngines::fallbackFor(AiModel::ClaudeHaiku45));
        $this->assertSame(AiModel::ClaudeHaiku45, SiteDesignEngines::fallbackFor(AiModel::Gpt6Luna));
    }

    private function oldSiteBrand(Page $page): void
    {
        SiteInventoryPage::create([
            'business_id' => $page->business_id,
            'location_id' => Location::where('business_id', $page->business_id)->value('id'),
            'url' => 'https://example.com',
            'status' => 'fetched',
            'fetched_at' => now(),
            'brand' => ['theme_color' => '#1e5aa8', 'colours' => ['#1e5aa8', '#e4572e'], 'fonts' => ['Merriweather 8301']],
        ]);
    }

    public function test_the_designer_is_given_the_brand_found_on_the_current_website(): void
    {
        $page = $this->page([['type' => 'hero', 'headline' => 'H']]);
        $this->oldSiteBrand($page);
        $this->fakeAnswer(json_encode(['theme' => 'warm-local', 'blocks' => [['type' => 'hero', 'headline' => 'New 8302']]]));

        app(SiteDesignGenerateAction::class)->handle($page->business_id, $page->id);

        Http::assertSent(fn ($r) => str_contains($r->body(), "Colours and fonts on the business's current website")
            && str_contains($r->body(), 'Merriweather 8301')
            && str_contains($r->body(), '#e4572e'));
    }

    public function test_a_page_that_keeps_the_sites_theme_is_not_given_the_old_brand(): void
    {
        $page = $this->page([['type' => 'hero', 'headline' => 'H']]);
        $this->oldSiteBrand($page);
        Business::whereKey($page->business_id)->update(['site_tokens' => json_encode(['theme' => 'warm-local'])]);
        $this->fakeAnswer(json_encode(['theme' => 'warm-local', 'blocks' => [['type' => 'hero', 'headline' => 'New 8303']]]));

        app(SiteDesignGenerateAction::class)->handle($page->business_id, $page->id, 'claude', true);

        Http::assertSent(fn ($r) => str_contains($r->body(), 'This site already uses the theme')
            && ! str_contains($r->body(), 'Merriweather 8301'));
    }

    public function test_the_hero_always_gets_a_picture_even_when_the_ai_asks_for_none(): void
    {
        Storage::fake('local');
        $page = $this->page([['type' => 'hero', 'headline' => 'H']]);
        Http::fake([
            'api.openai.com/v1/images/generations' => Http::response([
                'data' => [['b64_json' => '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=']],
            ], 200, ['Content-Type' => 'application/json']),
        ]);
        $this->fakeAnswer(json_encode([
            'theme' => 'warm-local',
            'blocks' => [
                ['type' => 'hero', 'headline' => 'Fallback headline 9201', 'variant' => 'centered'],
                ['type' => 'about', 'text' => 'A'],
            ],
        ]));

        app(SiteDesignGenerateAction::class)->handle($page->business_id, $page->id);

        $page->refresh();
        $hero = $page->draft_meta['designs']['claude']['blocks'][0];
        $this->assertNotEmpty($hero['image_path']);
        Storage::disk('local')->assertExists($hero['image_path']);
        $this->assertSame('split', $hero['variant']);
        $made = collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]->url(), 'images/generations'))->count();
        $this->assertSame(1, $made);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'images/generations') && str_contains($r->body(), 'Fallback headline 9201'));
    }

    public function test_the_ai_can_place_the_businesss_own_photo_and_logos_are_never_offered(): void
    {
        $page = $this->page([['type' => 'hero', 'headline' => 'H']]);
        $locationId = Location::where('business_id', $page->business_id)->value('id');
        $inventoryPage = SiteInventoryPage::create(['business_id' => $page->business_id, 'location_id' => $locationId, 'url' => 'https://example.com', 'status' => 'fetched', 'fetched_at' => now()]);
        foreach ([
            ['path' => 'inventory/logo-9203.png', 'alt' => 'Company logo 9203'],
            ['path' => 'inventory/shop-9202.jpg', 'alt' => 'Our shop front 9202'],
        ] as $photo) {
            SiteInventoryImage::create([
                'business_id' => $page->business_id,
                'page_id' => $inventoryPage->id,
                'source_url' => 'https://example.com/'.basename($photo['path']),
                'attribution' => 'example.com',
                'status' => 'stored',
                'path' => $photo['path'],
                'alt' => $photo['alt'],
                'width' => 1200,
                'height' => 800,
            ]);
        }
        $this->fakeAnswer(json_encode([
            'theme' => 'warm-local',
            'blocks' => [['type' => 'hero', 'headline' => 'Own photo 9204']],
            'images' => [['block_index' => 0, 'owner_photo' => 1]],
        ]));

        app(SiteDesignGenerateAction::class)->handle($page->business_id, $page->id);

        $page->refresh();
        $hero = $page->draft_meta['designs']['claude']['blocks'][0];
        $this->assertSame('inventory/shop-9202.jpg', $hero['image_path']);
        $this->assertSame('Our shop front 9202', $hero['image_alt']);
        $this->assertSame('split', $hero['variant']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.anthropic.com') && str_contains($r->body(), 'Our shop front 9202')
            && ! str_contains($r->body(), 'Company logo 9203'));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'images/generations'));
    }

    public function test_the_ai_is_offered_only_modern_fonts_and_an_old_font_it_names_is_dropped(): void
    {
        $page = $this->page([['type' => 'hero', 'headline' => 'H', 'image_path' => 'images/kept-9602.jpg']]);
        $this->fakeAnswer(json_encode([
            'theme' => 'bold-trade',
            'style' => ['type_pairing' => ['heading' => 'Trebuchet MS, sans-serif', 'body' => 'Inter, system-ui, sans-serif']],
            'blocks' => [['type' => 'hero', 'headline' => 'Fonts 9603']],
        ]));

        app(SiteDesignGenerateAction::class)->handle($page->business_id, $page->id);

        $page->refresh();
        $style = $page->draft_meta['designs']['claude']['style'];
        $this->assertSame('Inter, system-ui, sans-serif', $style['type_pairing']['body']);
        $this->assertArrayNotHasKey('heading', $style['type_pairing']);
        Http::assertSent(function ($r) {
            if (! str_contains($r->url(), 'api.anthropic.com')) {
                return false;
            }
            $prompt = (string) data_get(json_decode($r->body(), true), 'messages.0.content');

            return preg_match('/Fonts you may use: ([^\n]*)/', $prompt, $m) === 1
                && str_contains($m[1], 'Montserrat, system-ui, sans-serif')
                && ! str_contains($m[1], 'Trebuchet');
        });
    }

    public function test_on_a_template_the_ai_fills_its_sections_chooses_no_look_and_keeps_what_it_may_not_write(): void
    {
        $page = $this->page([
            ['type' => 'hero', 'headline' => 'Old headline'],
            ['type' => 'products', 'heading' => 'New from the kiln', 'items' => [['name' => 'Folk mug 7311', 'price_text' => '$38', 'image_path' => 'tenant/1/mug-7311.jpg']]],
            ['type' => 'gallery', 'items' => [['image_path' => 'tenant/1/shop-7312.jpg']]],
            ['type' => 'contact', 'phone' => '0100', 'hours' => [['day' => 'Saturday', 'open' => '10:00', 'close' => '18:00']], 'facts' => ['service_area' => 'Asheville 7313']],
        ]);
        app(SiteTemplateApplyAction::class)->handle($page->business_id, $page->id, 'maker-market');
        $this->fakeAnswer(json_encode([
            'theme' => 'bold-trade',
            'style' => ['palette' => ['primary' => '#c2410c']],
            'blocks' => [
                ['type' => 'hero', 'headline' => 'Pottery made by hand 7314', 'variant' => 'cover'],
                ['type' => 'team', 'heading' => 'Not in this template 7315', 'items' => [['name' => 'Sam', 'role' => 'Potter']]],
                ['type' => 'about', 'heading' => 'Our story', 'text' => 'Two potters 7316.'],
                ['type' => 'contact', 'phone' => '0100'],
            ],
            'explanation' => 'Filled the template.',
        ]));

        $res = app(SiteDesignGenerateAction::class)->handle($page->business_id, $page->id);

        $this->assertSame('ready', $res['status']);
        $page->refresh();
        $design = $page->draft_meta['designs']['claude'];
        $this->assertSame('maker-market', $design['template']);
        $this->assertNull($design['theme']);
        $this->assertNull($design['style']);
        $this->assertSame(['hero', 'about', 'contact', 'products', 'gallery'], array_column($design['blocks'], 'type'));
        $this->assertSame('Folk mug 7311', $design['blocks'][3]['items'][0]['name']);
        $this->assertSame('tenant/1/shop-7312.jpg', $design['blocks'][4]['items'][0]['image_path']);
        $this->assertSame('Saturday', $design['blocks'][2]['hours'][0]['day']);
        $this->assertSame('Asheville 7313', $design['blocks'][2]['facts']['service_area']);
        Http::assertSent(fn ($r) => str_contains($r->body(), 'Maker Market') && str_contains($r->body(), 'hero, about, reviews_strip, faq, cta_band, contact')
            && ! str_contains($r->body(), 'Themes (use one id)') && ! str_contains($r->body(), 'Fonts you may use'));

        $preview = app(PagePreview::class)->designHtml($page, 'claude');
        $this->assertStringContainsString('Pottery made by hand 7314', $preview);
        $this->assertStringContainsString('<li class="mm-product">', $preview);

        app(SiteDesignUseAction::class)->handle($page->business_id, $page->id, 'claude');
        app(SiteEditApplyAction::class)->handle($page->business_id, $page->id);
        $this->assertSame('maker-market', app(IndustryStartingPoints::class)->forBusiness($page->business_id)['template']);
    }

    public function test_an_ai_design_keeps_the_owners_opening_hours_and_stated_facts(): void
    {
        $page = $this->page([
            ['type' => 'hero', 'headline' => 'Old headline'],
            ['type' => 'contact', 'phone' => '0100', 'hours' => [['day' => 'Monday', 'open' => '08:00', 'close' => '17:00']], 'facts' => ['licence_number' => 'LIC-7317'], 'industry_facts' => [['label' => 'Emergency', 'value' => 'Yes 7318']]],
        ]);
        $this->fakeAnswer(json_encode([
            'theme' => 'bold-trade',
            'blocks' => [
                ['type' => 'hero', 'headline' => 'Designed 7319'],
                ['type' => 'contact', 'phone' => '0100', 'email' => 'a@example.com'],
            ],
        ]));

        app(SiteDesignGenerateAction::class)->handle($page->business_id, $page->id);

        $page->refresh();
        $design = $page->draft_meta['designs']['claude'];
        $this->assertSame('bold-trade', $design['theme']);
        $this->assertNull($design['template']);
        $contact = $design['blocks'][1];
        $this->assertSame('a@example.com', $contact['email']);
        $this->assertSame('Monday', $contact['hours'][0]['day']);
        $this->assertSame('LIC-7317', $contact['facts']['licence_number']);
        $this->assertSame('Yes 7318', $contact['industry_facts'][0]['value']);
        Http::assertSent(fn ($r) => str_contains($r->body(), 'Themes (use one id)'));
    }
}
