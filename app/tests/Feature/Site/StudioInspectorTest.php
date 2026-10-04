<?php

namespace Tests\Feature\Site;

use App\Enums\UserRole;
use App\Livewire\Site\Studio;
use App\Models\Business;
use App\Models\User;
use App\Modules\X103\Actions\SiteTemplateApplyAction;
use App\Modules\X103\Jobs\SiteDesignJob;
use App\Modules\X103\Models\Page;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class StudioInspectorTest extends TestCase
{
    public function test_owner_edits_hero_headline(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant(['name' => 'Inspector Owner', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($business->id);

        $page = Page::create([
            'business_id' => $business->id, 'slug' => 'p'.rand(), 'title' => 'T',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Old', 'subline' => 'Old'],
                ['type' => 'about', 'text' => 'Unchanged'],
            ],
            'is_published' => false,
        ]);

        $business->update(['site_tokens' => ['palette' => 'test']]);

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('selectBlock', 0)
            ->set('blockHeadline', 'New Headline')
            ->call('setBlockField')
            ->assertSet('error', null)
            ->assertSet('success', 'Headline saved.');

        $page->refresh();
        $this->assertSame('New Headline', $page->draft_blocks[0]['headline']);
        $this->assertCount(1, $page->draft_meta['undo'] ?? []);
        $this->assertSame(['palette' => 'test'], $page->draft_meta['undo'][0]['site_tokens']);
    }

    public function test_manager_may_look_and_not_change(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $business = TestCase::provisionTenant(['name' => 'Inspector Manager', 'currency' => 'USD', 'owner_user_id' => $manager->id]);

        $this->actingAs($manager);
        Tenancy::set($business->id);

        $page = Page::create([
            'business_id' => $business->id, 'slug' => 'p'.rand(), 'title' => 'T',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Old', 'subline' => 'Old'],
                ['type' => 'about', 'text' => 'Unchanged'],
            ],
            'is_published' => false,
        ]);

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('selectBlock', 0)
            ->set('blockHeadline', 'New Headline')
            ->call('setBlockField')
            ->assertForbidden();

        $page->refresh();
        $this->assertSame('Old', $page->draft_blocks[0]['headline']);
    }

    public function test_refusal_surfaced_and_nothing_changes(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant(['name' => 'Inspector Refusal', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($business->id);

        $page = Page::create([
            'business_id' => $business->id, 'slug' => 'p'.rand(), 'title' => 'T',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Old', 'subline' => 'Old'],
                ['type' => 'about', 'text' => 'Unchanged'],
            ],
            'is_published' => false,
        ]);

        $originalBlocks = $page->draft_blocks;

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->set('selectedBlockIndex', 99)
            ->set('blockHeadline', 'New Headline')
            ->call('setBlockField')
            ->assertSet('success', null)
            ->assertNotSet('error', null);

        $page->refresh();
        $this->assertEquals($originalBlocks, $page->draft_blocks);
    }

    public function test_ask_then_edit_and_the_proposal_moves_while_the_draft_does_not(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant(['name' => 'Inspector Owner', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($business->id);

        $page = Page::create([
            'business_id' => $business->id, 'slug' => 'p'.rand(), 'title' => 'T',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Old', 'subline' => 'Old'],
                ['type' => 'about', 'text' => 'Unchanged'],
            ],
            'is_published' => false,
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'msg_edit',
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'patches' => [
                            ['op' => 'set_string', 'block_index' => 0, 'field' => 'headline', 'value' => 'NEWPROPOSED'],
                        ],
                        'explanation' => 'Updated hero.',
                    ])]],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $lw = Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->set('request', 'Change hero')
            ->call('ask');

        $lw->call('selectBlock', 0)
            ->set('blockHeadline', 'Edited Proposed')
            ->call('setBlockField');

        $page->refresh();
        $this->assertSame('Edited Proposed', $page->draft_meta['pending_edit']['blocks'][0]['headline']);
        $this->assertSame('Old', $page->draft_blocks[0]['headline']);
    }

    public function test_after_discard_draft_blocks_is_unchanged_from_before_the_ask(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant(['name' => 'Inspector Owner', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($business->id);

        $page = Page::create([
            'business_id' => $business->id, 'slug' => 'p'.rand(), 'title' => 'T',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Old', 'subline' => 'Old'],
                ['type' => 'about', 'text' => 'Unchanged'],
            ],
            'is_published' => false,
        ]);

        $originalBlocks = $page->draft_blocks;

        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'msg_edit',
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'patches' => [
                            ['op' => 'set_string', 'block_index' => 0, 'field' => 'headline', 'value' => 'NEWPROPOSED'],
                        ],
                        'explanation' => 'Updated hero.',
                    ])]],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $lw = Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->set('request', 'Change hero')
            ->call('ask');

        $lw->call('selectBlock', 0)
            ->set('blockHeadline', 'Edited Proposed')
            ->call('setBlockField');

        $lw->call('discardProposal');

        $page->refresh();
        $this->assertEquals($originalBlocks, $page->draft_blocks);
    }

    public function test_a_refused_ask_leaves_success_null_and_sets_error(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant(['name' => 'Inspector Owner', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($business->id);

        $page = Page::create([
            'business_id' => $business->id, 'slug' => 'p'.rand(), 'title' => 'T',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Old', 'subline' => 'Old'],
                ['type' => 'about', 'text' => 'Unchanged'],
            ],
            'is_published' => false,
        ]);

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->set('request', '')
            ->call('ask')
            ->assertSet('success', null)
            ->assertNotSet('error', null);
    }

    public function test_make_it_look_great_asks_the_ai_for_a_design_and_proposes_it(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant(['name' => 'Inspector Owner', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($business->id);

        $page = Page::create([
            'business_id' => $business->id, 'slug' => 'p'.rand(), 'title' => 'T',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Old', 'subline' => 'Old'],
                ['type' => 'about', 'text' => 'Unchanged'],
            ],
            'is_published' => false,
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'msg_edit',
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'patches' => [
                            ['op' => 'set_string', 'block_index' => 0, 'field' => 'variant', 'value' => 'centered'],
                            ['op' => 'set_string', 'block_index' => 0, 'field' => 'cta_label', 'value' => 'Call us 7201'],
                            ['op' => 'set_string', 'block_index' => 0, 'field' => 'cta_url', 'value' => 'tel:+15550107201'],
                            ['op' => 'add_block', 'block_index' => 2, 'type' => 'cta_band', 'fields' => ['heading' => 'Ready when you are 7202', 'label' => 'Book now', 'url' => 'https://example.com/book']],
                        ],
                        'explanation' => 'A bolder banner and a call to action.',
                    ])]],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('askDesign')
            ->assertSet('error', null);

        $page->refresh();
        $proposed = $page->draft_meta['pending_edit']['blocks'];
        $this->assertSame('centered', $proposed[0]['variant']);
        $this->assertSame('tel:+15550107201', $proposed[0]['cta_url']);
        $this->assertSame('cta_band', $proposed[2]['type']);
        $this->assertSame('Old', $page->draft_blocks[0]['headline']);
        Http::assertSent(fn ($r) => str_contains($r->body(), 'look modern and professional'));
    }

    public function test_design_with_all_four_ais_queues_each_and_shows_the_chosen_design_when_ready(): void
    {
        Queue::fake();
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant(['name' => 'Inspector Owner', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($business->id);

        $page = Page::create([
            'business_id' => $business->id, 'slug' => 'p'.rand(), 'title' => 'T',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Old', 'subline' => 'Old'],
                ['type' => 'about', 'text' => 'Unchanged'],
            ],
            'is_published' => false,
        ]);

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('designWithAll')
            ->assertSet('error', null);

        Queue::assertPushed(SiteDesignJob::class, 4);
        Queue::assertPushed(SiteDesignJob::class, fn (SiteDesignJob $job) => $job->engine === 'gemini' && $job->pageId === $page->id && $job->businessId === $business->id);
        $page->refresh();
        $this->assertEqualsCanonicalizing(['claude', 'chatgpt', 'gemini', 'grok'], array_keys($page->draft_meta['designs']));
        $this->assertSame('running', $page->draft_meta['designs']['grok']['status']);

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('designWithAi', 'grok')
            ->assertSet('error', 'That AI is already designing this page.');

        $meta = $page->draft_meta;
        $meta['designs']['gemini'] = ['status' => 'ready', 'theme' => 'warm-local', 'style' => null, 'blocks' => [['type' => 'hero', 'headline' => 'Designed by Gemini 7401']], 'explanation' => 'Warm.', 'model' => 'google-gemini-3.1-pro'];
        $page->update(['draft_meta' => $meta]);

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->assertViewHas('previewHtml', fn ($html) => ! str_contains($html, 'Designed by Gemini 7401'))
            ->set('showDesign', 'grok')
            ->assertViewHas('previewHtml', fn ($html) => ! str_contains($html, 'Designed by Gemini 7401'))
            ->set('showDesign', 'gemini')
            ->assertViewHas('previewHtml', fn ($html) => str_contains($html, 'Designed by Gemini 7401') && str_contains($html, '.site-block.about .site-block__inner { max-width: 44rem; text-align: center; }'));
    }

    public function test_a_theme_changes_the_look_offers_publish_and_undo_brings_the_old_look_back(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant(['name' => 'Inspector Owner', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($business->id);

        $page = Page::create([
            'business_id' => $business->id, 'slug' => 'p'.rand(), 'title' => 'T',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Old', 'subline' => 'Old'],
                ['type' => 'about', 'text' => 'Unchanged'],
            ],
            'is_published' => false,
        ]);
        $before = Business::find($business->id)->site_tokens;

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('applyTheme', 'bold-trade')
            ->assertSet('error', null)
            ->assertViewHas('previewHtml', fn ($html) => str_contains($html, 'border-top: 8px solid var(--color-primary)'));

        $tokens = Business::find($business->id)->site_tokens;
        $this->assertSame('bold-trade', $tokens['theme']);
        $this->assertSame('#f5b800', $tokens['palette']['primary']);
        $page->refresh();
        $this->assertTrue($page->draft_meta['look_changed']);
        $this->assertEquals([['type' => 'hero', 'headline' => 'Old', 'subline' => 'Old'], ['type' => 'about', 'text' => 'Unchanged']], $page->draft_blocks);

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('undo')
            ->assertSet('error', null);
        $this->assertEquals($before, Business::find($business->id)->site_tokens);

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('applyTheme', 'no-such-theme')
            ->assertSet('error', 'There is no such theme.');
    }

    public function test_using_an_ai_design_previews_it_with_its_theme_and_apply_keeps_both(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant(['name' => 'Inspector Owner', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($business->id);

        $page = Page::create([
            'business_id' => $business->id, 'slug' => 'p'.rand(), 'title' => 'T',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Old', 'subline' => 'Old'],
                ['type' => 'about', 'text' => 'Unchanged'],
            ],
            'is_published' => false,
        ]);
        $meta = $page->draft_meta ?? [];
        $meta['designs']['grok'] = ['status' => 'ready', 'theme' => 'warm-local', 'style' => null, 'blocks' => [['type' => 'hero', 'headline' => 'Grok design 7501'], ['type' => 'about', 'text' => 'About 7502']], 'explanation' => 'Warm.', 'model' => 'xai-grok-4.7'];
        $page->update(['draft_meta' => $meta]);

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('useDesign', 'grok')
            ->assertSet('error', null)
            ->assertViewHas('previewHtml', fn ($html) => str_contains($html, 'Grok design 7501') && str_contains($html, '.site-block.about .site-block__inner { max-width: 44rem; text-align: center; }'))
            ->call('useDesign', 'grok')
            ->assertSet('error', 'Apply or discard the proposal you are previewing first.')
            ->call('applyProposal')
            ->assertSet('error', null);

        $page->refresh();
        $this->assertEquals([['type' => 'hero', 'headline' => 'Grok design 7501'], ['type' => 'about', 'text' => 'About 7502']], $page->draft_blocks);
        $tokens = Business::find($business->id)->site_tokens;
        $this->assertSame('warm-local', $tokens['theme']);
        $this->assertSame('#a23e2a', $tokens['palette']['primary']);
    }

    public function test_an_owner_with_no_pages_can_build_the_whole_site_in_one_click(): void
    {
        Bus::fake();
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant(['name' => 'Inspector Owner', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($business->id);

        $component = Livewire::test(Studio::class)
            ->assertSee('Build my whole site with AI')
            ->call('buildWholeSite')
            ->assertSet('error', null);

        $home = Page::where('business_id', $business->id)->where('slug', 'home')->firstOrFail();
        $component->assertSet('pageId', $home->id);
        $this->assertSame(4, Page::where('business_id', $business->id)->count());
        Bus::assertChained([SiteDesignJob::class, SiteDesignJob::class, SiteDesignJob::class, SiteDesignJob::class]);
    }

    public function test_corners_change_the_look_and_undo_brings_the_old_corners_back(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant(['name' => 'Inspector Owner', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($business->id);

        $page = Page::create([
            'business_id' => $business->id, 'slug' => 'p'.rand(), 'title' => 'T',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Old', 'subline' => 'Old'],
                ['type' => 'about', 'text' => 'Unchanged'],
            ],
            'is_published' => false,
        ]);
        $before = Business::find($business->id)->site_tokens;

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('setCorners', 'round')
            ->assertSet('error', null)
            ->assertViewHas('previewHtml', fn ($html) => str_contains($html, '/* corners: round */'));

        $this->assertSame('round', Business::find($business->id)->site_tokens['corners']);
        $page->refresh();
        $this->assertTrue($page->draft_meta['look_changed']);
        $this->assertEquals([['type' => 'hero', 'headline' => 'Old', 'subline' => 'Old'], ['type' => 'about', 'text' => 'Unchanged']], $page->draft_blocks);

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('undo')
            ->assertSet('error', null);
        $this->assertEquals($before, Business::find($business->id)->site_tokens);

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('setCorners', 'blob')
            ->assertSet('error', 'There is no such corner style.');
        $this->assertEquals($before, Business::find($business->id)->site_tokens);
    }

    public function test_an_ai_design_can_bring_its_corner_style(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant(['name' => 'Inspector Owner', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($business->id);

        $page = Page::create([
            'business_id' => $business->id, 'slug' => 'p'.rand(), 'title' => 'T',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Old', 'subline' => 'Old'],
                ['type' => 'about', 'text' => 'Unchanged'],
            ],
            'is_published' => false,
        ]);
        $meta = $page->draft_meta ?? [];
        $meta['designs']['grok'] = ['status' => 'ready', 'theme' => 'warm-local', 'style' => ['corners' => 'soft'], 'blocks' => [['type' => 'hero', 'headline' => 'Soft design 8204']], 'explanation' => 'Soft.', 'model' => 'xai-grok-4.3'];
        $page->update(['draft_meta' => $meta]);

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('useDesign', 'grok')
            ->assertSet('error', null)
            ->assertViewHas('previewHtml', fn ($html) => str_contains($html, 'Soft design 8204') && str_contains($html, '/* corners: soft */'))
            ->call('applyProposal')
            ->assertSet('error', null);

        $tokens = Business::find($business->id)->site_tokens;
        $this->assertSame('soft', $tokens['corners']);
        $this->assertSame('warm-local', $tokens['theme']);
    }

    public function test_make_it_look_great_on_a_template_starts_the_designer_and_proposes_its_design_when_ready(): void
    {
        // Fixture taken from test_design_with_all_four_ais_queues_each_and_shows_the_chosen_design_when_ready.
        Queue::fake();
        Http::fake();
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant(['name' => 'Inspector Owner', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($business->id);

        $page = Page::create([
            'business_id' => $business->id, 'slug' => 'p'.rand(), 'title' => 'T',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Old', 'subline' => 'Old'],
                ['type' => 'about', 'text' => 'Unchanged'],
            ],
            'is_published' => false,
        ]);
        app(SiteTemplateApplyAction::class)->handle($business->id, $page->id, 'trades-pro');

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('askDesign')
            ->assertSet('error', null)
            ->assertSet('success', 'Designing your page in the Trades Pro template — the words and a picture. It takes a minute or two; the proposal appears here when it is ready.');

        Queue::assertPushed(SiteDesignJob::class, 1);
        Queue::assertPushed(SiteDesignJob::class, fn (SiteDesignJob $job) => $job->engine === 'claude' && $job->pageId === $page->id && $job->proposeWhenReady);
        Http::assertNothingSent();
        $page->refresh();
        $this->assertSame('running', $page->draft_meta['designs']['claude']['status']);
        $this->assertArrayNotHasKey('pending_edit', $page->draft_meta);
    }
}
