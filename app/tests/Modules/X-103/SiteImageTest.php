<?php

namespace Tests\Modules\X103;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use App\Modules\X103\Domain\PagePreview;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Ui\Pages;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SiteImageTest extends TestCase
{
    public function test_editor_generates_and_previews_hero_picture(): void
    {
        Storage::fake('local');

        $business = Business::factory()->create();
        Tenancy::set($business->id);

        $page = Page::create([
            'business_id' => $business->id, 'slug' => 'test', 'title' => 'Test', 'is_published' => false,
            'draft_blocks' => [['type' => 'hero', 'headline' => 'Old']],
        ]);

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business->update(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'blocks' => [['type' => 'hero', 'headline' => 'New']],
                    'explanation' => 'Added a photo.',
                    'images' => [['block_index' => 0, 'description' => 'A shiny van']],
                ])]]],
                'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
            ], 200),
            'api.openai.com/v1/images/generations' => Http::response([
                'data' => [['b64_json' => '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        $lw = Livewire::test(Pages::class, ['businessId' => $business->id])
            ->set("editRequest.{$page->id}", 'add a photo')
            ->call('askEdit', $page->id);

        $page->refresh();
        $blocks = $page->draft_meta['pending_edit']['blocks'];

        $this->assertStringStartsWith("site-inventory/{$business->id}/ai-", $blocks[0]['image_path']);
        $this->assertStringEndsWith('.jpg', $blocks[0]['image_path']);

        Storage::disk('local')->assertExists($blocks[0]['image_path']);

        $lw = $lw = Livewire::test(Pages::class, ['businessId' => $business->id]);
        $html = app(PagePreview::class)->html($page, true);
        $this->assertStringContainsString('data:image/jpeg;base64,', $html);
    }

    public function test_third_picture_beyond_cap_is_not_generated(): void
    {
        Storage::fake('local');
        $business = Business::factory()->create();
        Tenancy::set($business->id);

        $page = Page::create([
            'business_id' => $business->id, 'slug' => 'test', 'title' => 'Test', 'is_published' => false,
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'test'],
                ['type' => 'hero', 'headline' => 'test'],
                ['type' => 'hero', 'headline' => 'test'],
            ],
        ]);

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business->update(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'blocks' => [
                        ['type' => 'hero', 'headline' => 'test'],
                        ['type' => 'hero', 'headline' => 'test'],
                        ['type' => 'hero', 'headline' => 'test'],
                    ],
                    'explanation' => 'Added photos.',
                    'images' => [
                        ['block_index' => 0, 'description' => 'Pic 1'],
                        ['block_index' => 1, 'description' => 'Pic 2'],
                        ['block_index' => 2, 'description' => 'Pic 3'],
                    ],
                ])]]],
            ], 200),
            'api.openai.com/v1/images/generations' => Http::response([
                'data' => [['b64_json' => '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=']],
            ], 200),
        ]);

        $lw = Livewire::test(Pages::class, ['businessId' => $business->id])
            ->set("editRequest.{$page->id}", 'add photos')
            ->call('askEdit', $page->id);

        Http::assertSentCount(3); // 1 chat + 2 image calls
    }

    public function test_400_on_image_call_leaves_proposal_intact_and_adds_notes(): void
    {
        Storage::fake('local');
        $business = Business::factory()->create();
        Tenancy::set($business->id);

        $page = Page::create([
            'business_id' => $business->id, 'slug' => 'test', 'title' => 'Test', 'is_published' => false,
            'draft_blocks' => [['type' => 'hero', 'headline' => 'test']],
        ]);

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business->update(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'blocks' => [['type' => 'hero', 'headline' => 'test']],
                    'explanation' => 'Added photo.',
                    'images' => [['block_index' => 0, 'description' => 'A shiny van']],
                ])]]],
            ], 200),
            'api.openai.com/v1/images/generations' => Http::response([
                'error' => ['message' => 'bad request'],
            ], 400),
        ]);

        $lw = Livewire::test(Pages::class, ['businessId' => $business->id])
            ->set("editRequest.{$page->id}", 'add a photo')
            ->call('askEdit', $page->id);

        $page->refresh();
        $meta = $page->draft_meta['pending_edit'];

        $this->assertSame('Added photo.', $meta['explanation']);
        $this->assertStringContainsString('Could not generate picture', $meta['image_notes']);
    }

    public function test_non_image_bytes_are_rejected(): void
    {
        Storage::fake('local');
        $business = Business::factory()->create();
        Tenancy::set($business->id);

        $page = Page::create([
            'business_id' => $business->id, 'slug' => 'test', 'title' => 'Test', 'is_published' => false,
            'draft_blocks' => [['type' => 'hero', 'headline' => 'test']],
        ]);

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business->update(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'blocks' => [['type' => 'hero', 'headline' => 'test']],
                    'explanation' => 'Added photo.',
                    'images' => [['block_index' => 0, 'description' => 'A shiny van']],
                ])]]],
            ], 200),
            'api.openai.com/v1/images/generations' => Http::response([
                'data' => [['b64_json' => 'PGh0bWw+aGk8L2h0bWw+']],
            ], 200),
        ]);

        Livewire::test(Pages::class, ['businessId' => $business->id])
            ->set("editRequest.{$page->id}", 'add a photo')
            ->call('askEdit', $page->id);

        $page->refresh();
        $meta = $page->draft_meta['pending_edit'];

        $this->assertSame('Added photo.', $meta['explanation']);
        $this->assertStringContainsString('Could not generate picture', $meta['image_notes']);
        $this->assertEmpty(Storage::disk('local')->allFiles("site-inventory/{$business->id}"));
    }
}
