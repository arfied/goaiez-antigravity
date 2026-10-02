<?php

namespace Tests\Feature\Site;

use App\Enums\UserRole;
use App\Livewire\Site\Studio;
use App\Models\Business;
use App\Models\User;
use App\Modules\X103\Jobs\SiteDesignJob;
use App\Modules\X103\Models\Page;
use App\Support\Tenancy;
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

    public function test_design_with_ai_queues_the_designer_and_shows_its_page_when_ready(): void
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
            ->call('designWithAi')
            ->assertSet('error', null)
            ->assertSet('showDesign', true);

        Queue::assertPushed(SiteDesignJob::class, fn (SiteDesignJob $job) => $job->pageId === $page->id && $job->businessId === $business->id);
        $page->refresh();
        $this->assertSame('running', $page->draft_meta['design']['status']);

        $meta = $page->draft_meta;
        $meta['design'] = ['status' => 'ready', 'style' => 'h1{color:red}', 'html' => '<main><h1>Designed by AI 7401</h1></main>', 'images' => []];
        $page->update(['draft_meta' => $meta]);

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->assertViewHas('previewHtml', fn ($html) => ! str_contains($html, 'Designed by AI 7401'))
            ->set('showDesign', true)
            ->assertViewHas('previewHtml', fn ($html) => str_contains($html, 'Designed by AI 7401') && str_contains($html, 'h1{color:red}'));
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
}
