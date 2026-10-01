<?php

namespace Tests\Modules\X103;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X103\Domain\BlockPatchSchema;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Ui\Pages;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class SitePatchTest extends TestCase
{
    public function test_ask_proposes_patches_and_apply_changes_only_those_blocks()
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant(['name' => 'SitePatch', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($business->id);

        $page = Page::create([
            'business_id' => $business->id, 'slug' => 'p'.rand(), 'title' => 'T',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Old', 'subline' => 'Old'],
                ['type' => 'about', 'text' => 'Unchanged'],
            ],
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'msg_edit',
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'patches' => [
                            ['op' => 'set_string', 'block_index' => 0, 'field' => 'headline', 'value' => 'New Headline'],
                            ['op' => 'set_string', 'block_index' => 0, 'field' => 'subline', 'value' => 'New Subline'],
                        ],
                        'explanation' => 'Updated hero only.',
                    ])]],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        Livewire::test(Pages::class)
            ->set('editRequest', [$page->id => 'Change hero'])
            ->call('askEdit', $page->id);

        $page->refresh();
        $blocks = $page->draft_meta['pending_edit']['blocks'];

        $this->assertSame('New Headline', $blocks[0]['headline']);
        $this->assertSame('New Subline', $blocks[0]['subline']);
        $this->assertEquals(['type' => 'about', 'text' => 'Unchanged'], $blocks[1]);
    }

    public function test_a_refused_patch_set_leaves_the_draft_untouched_and_tells_the_owner()
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant(['name' => 'SitePatch2', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($business->id);

        $page = Page::create([
            'business_id' => $business->id, 'slug' => 'p'.rand(), 'title' => 'T',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Old'],
            ],
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'msg_edit',
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'patches' => [
                            ['op' => 'set_string', 'block_index' => 999, 'field' => 'headline', 'value' => 'New Headline'],
                        ],
                        'explanation' => 'Updated out of bounds.',
                    ])]],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        Livewire::test(Pages::class)
            ->set('editRequest', [$page->id => 'Change hero'])
            ->call('askEdit', $page->id)
            ->assertSet('success', null)
            ->assertSet('error', 'patch 0: block_index out of range');

        $page->refresh();
        $this->assertArrayNotHasKey('pending_edit', $page->draft_meta ?? []);
    }

    public function test_the_model_schema_offers_four_ops_and_the_applier_five()
    {
        $schema = BlockPatchSchema::schema();
        $ops = $schema['properties']['patches']['items']['properties']['op']['enum'];

        $this->assertSame(BlockPatchSchema::MODEL_OPS, $ops);
        $this->assertNotContains('set_image_list', $ops);
        $this->assertContains('set_image_list', BlockPatchSchema::APPLIER_OPS);
        $this->assertCount(4, $ops);
        $this->assertCount(5, BlockPatchSchema::APPLIER_OPS);
    }

    public function test_a_requested_picture_becomes_a_synthesised_image_patch()
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant(['name' => 'SitePatch3', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($business->id);

        $page = Page::create([
            'business_id' => $business->id, 'slug' => 'p'.rand(), 'title' => 'T',
            'draft_blocks' => [
                ['type' => 'gallery', 'items' => []],
            ],
        ]);

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'id' => 'msg_edit',
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'patches' => [],
                        'images' => [
                            ['block_index' => 0, 'description' => 'A photo of a dog.'],
                        ],
                        'explanation' => 'Generated a photo.',
                    ])]],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ], 200, ['Content-Type' => 'application/json']),
            'api.openai.com/v1/images/generations' => Http::response([
                'data' => [['b64_json' => '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=']],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        Livewire::test(Pages::class)
            ->set('editRequest', [$page->id => 'Add photo'])
            ->call('askEdit', $page->id)
            ->assertSet('success', 'Proposed 1 edits with openai-4o-mini — review it below, then Apply or Discard. Made 1 picture(s) for it.');

        $page->refresh();
        $blocks = $page->draft_meta['pending_edit']['blocks'];
        $this->assertCount(1, $blocks[0]['items']);
    }
}
