<?php

namespace Tests\Feature\Site;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X103\Models\Page;
use App\Livewire\Site\Studio;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class StudioLoopTest extends TestCase
{
    public function test_the_studio_walks_ask_then_preview_then_apply_then_publish()
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant(['name' => 'StudioLoop', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
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
            ->call('ask')
            ->assertSet('request', '');

        $page->refresh();
        $this->assertArrayHasKey('pending_edit', $page->draft_meta);
        $this->assertSame('Old', $page->draft_blocks[0]['headline']);

        $lw->assertSee('NEWPROPOSED')
           ->assertSee('Previewing AI proposal');

        $lw->call('applyProposal');

        $page->refresh();
        $this->assertArrayNotHasKey('pending_edit', $page->draft_meta);
        $this->assertSame('NEWPROPOSED', $page->draft_blocks[0]['headline']);
        $this->assertArrayHasKey('undo', $page->draft_meta);

        // the HTTP mock for the deploy platform
        Http::fake([
            'api.vercel.com/*' => Http::response(['id' => 'dpl_123', 'readyState' => 'READY', 'url' => 'example.vercel.app']),
        ]);

        $lw->call('publish', $page->id);
        
        $this->assertStringContainsString('is live at', $lw->get('success'));
    }

    public function test_a_manager_may_look_but_not_change()
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $business = TestCase::provisionTenant(['name' => 'StudioLoopM', 'currency' => 'USD', 'owner_user_id' => $manager->id]);
        
        $this->actingAs($manager);
        Tenancy::set($business->id);

        $page = Page::create([
            'business_id' => $business->id, 'slug' => 'p'.rand(), 'title' => 'T',
            'draft_blocks' => [],
        ]);

        Livewire::test(Studio::class)
            ->assertOk();

        Livewire::test(Studio::class)
           ->set('pageId', $page->id)
           ->call('ask')
           ->assertForbidden();

        Livewire::test(Studio::class)
           ->set('pageId', $page->id)
           ->call('applyProposal')
           ->assertForbidden();

        Livewire::test(Studio::class)
           ->set('pageId', $page->id)
           ->call('publish', $page->id)
           ->assertForbidden();
    }

    public function test_apply_refuses_when_nothing_is_proposed()
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant(['name' => 'StudioLoopE', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($business->id);

        $page = Page::create([
            'business_id' => $business->id, 'slug' => 'p'.rand(), 'title' => 'T',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Old', 'subline' => 'Old'],
            ],
        ]);

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('applyProposal')
            ->assertSet('error', 'Nothing proposed.')
            ->assertSet('success', null);

        $page->refresh();
        $this->assertSame('Old', $page->draft_blocks[0]['headline']);
    }

    public function test_discard_removes_the_proposal_and_leaves_the_draft()
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant(['name' => 'StudioLoopD', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($business->id);

        $page = Page::create([
            'business_id' => $business->id, 'slug' => 'p'.rand(), 'title' => 'T',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Old', 'subline' => 'Old'],
            ],
            'draft_meta' => [
                'pending_edit' => [
                    'blocks' => [
                        ['type' => 'hero', 'headline' => 'New', 'subline' => 'Old'],
                    ],
                ]
            ],
        ]);

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('discardProposal')
            ->assertSet('success', 'Discarded.');

        $page->refresh();
        $this->assertArrayNotHasKey('pending_edit', $page->draft_meta);
        $this->assertSame('Old', $page->draft_blocks[0]['headline']);
    }
}
