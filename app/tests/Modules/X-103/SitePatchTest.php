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
            ->assertSet('error', 'The AI proposed a change that could not be applied, so nothing was proposed. Try asking again in different words.');

        $page->refresh();
        $this->assertArrayNotHasKey('pending_edit', $page->draft_meta ?? []);
    }

    /**
     * The op sets are pinned by MEMBERSHIP, not by size.
     *
     * This test used to assert `assertCount(4, $ops)` and `assertCount(5, …)` and was named for those two
     * numbers. It fired correctly when `add_block` was added, which is what a tripwire is for — but a count
     * cannot tell a deliberate widening from a rename or a swap, and the name went stale the moment the
     * numbers moved. Exact lists catch all three.
     *
     * ⛔ The two assertions that are NOT bookkeeping:
     *   - `set_image_list` must never appear in MODEL_OPS. The model may not request an image list; the
     *     server synthesises those in SiteEditProposeAction after generating a picture, so a model that
     *     could ask for one could point a page at an arbitrary path.
     *   - Neither `set_image` nor `set_string_list` may appear in MODEL_OPS: the first is the server's hero-picture op, the second could write a list without set_items' guard against file paths.
     *   - ADDABLE_TYPES is every section type except gallery (image files) and form (needs a form definition) — owner ruling 2026-10-02 "AI may write anything". Changing it still costs a deliberate edit to this test.
     */
    public function test_the_op_sets_and_the_addable_types_are_pinned_by_membership()
    {
        $schema = BlockPatchSchema::schema();
        $ops = $schema['properties']['patches']['items']['properties']['op']['enum'];

        $this->assertSame(BlockPatchSchema::MODEL_OPS, $ops);
        $this->assertNotContains('set_image_list', $ops);
        $this->assertNotContains('set_image', $ops);
        $this->assertNotContains('set_string_list', $ops);
        $this->assertNotContains('set_item_string', $ops);
        $this->assertContains('set_image_list', BlockPatchSchema::APPLIER_OPS);

        $this->assertSame(['set_string', 'set_items', 'remove', 'move', 'add_block'], BlockPatchSchema::MODEL_OPS);
        $this->assertSame(['set_string', 'set_items', 'set_item_string', 'set_image', 'set_image_list', 'remove', 'move', 'add_block'], BlockPatchSchema::APPLIER_OPS);
        $this->assertSame(['hero', 'about', 'faq', 'services', 'reviews_strip', 'team', 'booking_button', 'booking_form', 'contact', 'video_embed'], BlockPatchSchema::ADDABLE_TYPES);
        $this->assertNotContains('gallery', BlockPatchSchema::ADDABLE_TYPES);
        $this->assertNotContains('image_path', BlockPatchSchema::MODEL_FIELDS);
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

    public function test_a_requested_hero_picture_lands_through_the_server_only_op()
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant(['name' => 'SitePatchHero', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($business->id);

        $page = Page::create([
            'business_id' => $business->id, 'slug' => 'p'.rand(), 'title' => 'T',
            'draft_blocks' => [['type' => 'hero', 'headline' => 'H']],
        ]);

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'id' => 'msg_edit',
                'choices' => [['message' => ['content' => json_encode([
                    'patches' => [],
                    'images' => [['block_index' => 0, 'description' => 'A photo of a roof.']],
                    'explanation' => 'Generated a photo.',
                ])]]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ], 200, ['Content-Type' => 'application/json']),
            'api.openai.com/v1/images/generations' => Http::response([
                'data' => [['b64_json' => '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=']],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        Livewire::test(Pages::class)
            ->set('editRequest', [$page->id => 'Add a photo'])
            ->call('askEdit', $page->id)
            ->assertSet('error', null);

        $page->refresh();
        $hero = $page->draft_meta['pending_edit']['blocks'][0];
        $this->assertIsString($hero['image_path']);
        $this->assertNotSame('', $hero['image_path']);
    }

    public function test_a_model_patch_outside_the_model_ops_is_never_applied()
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant(['name' => 'SitePatchUnsafe', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($business->id);

        $page = Page::create([
            'business_id' => $business->id, 'slug' => 'p'.rand(), 'title' => 'T',
            'draft_blocks' => [['type' => 'gallery', 'items' => []]],
        ]);

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'id' => 'msg_edit',
                'choices' => [['message' => ['content' => json_encode([
                    'patches' => [['op' => 'set_image_list', 'block_index' => 0, 'field' => 'items', 'images' => [['image_path' => 'some/other/file.jpg']]]],
                    'explanation' => 'x',
                ])]]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        Livewire::test(Pages::class)
            ->set('editRequest', [$page->id => 'Use that picture'])
            ->call('askEdit', $page->id)
            ->assertSet('error', 'The AI proposed a change it is not allowed to make, so nothing was proposed. Try asking again.');

        $page->refresh();
        $this->assertNull($page->draft_meta['pending_edit'] ?? null);
        $this->assertSame([], $page->draft_blocks[0]['items']);
    }
}
