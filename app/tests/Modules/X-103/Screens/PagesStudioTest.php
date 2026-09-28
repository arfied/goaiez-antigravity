<?php

namespace Tests\Modules\X103\Screens;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Ui\Pages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PagesStudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_studio_renders_status_pills()
    {
        $user = User::factory()->create(['role' => UserRole::Owner->value]);
        $business = Business::factory()->create(['owner_user_id' => $user->id]);

        Page::create([
            'business_id' => $business->id,
            'slug' => 'published-page',
            'title' => 'Published',
            'is_published' => true,
        ]);

        Page::create([
            'business_id' => $business->id,
            'slug' => 'draft-page',
            'title' => 'Draft',
            'is_published' => false,
        ]);

        $this->actingAs($user)
            ->get(route('x-103.pages'))
            ->assertOk()
            ->assertSeeText('Live')
            ->assertSeeText('Draft');
    }

    public function test_pages_studio_renders_editor()
    {
        $user = User::factory()->create(['role' => UserRole::Owner->value]);
        $business = Business::factory()->create(['owner_user_id' => $user->id]);

        $page = Page::create([
            'business_id' => $business->id,
            'slug' => 'edit-page',
            'title' => 'Edit',
            'is_published' => false,
            'draft_meta' => [
                'pending_edit' => [
                    'explanation' => 'I proposed this change.',
                    'blocks' => [],
                ],
            ],
        ]);

        $this->actingAs($user)
            ->get(route('x-103.pages', ['edit' => $page->id]))
            ->assertOk()
            ->assertSee('Say what to change') // Ask input heading
            ->assertSee('Proposed') // Segmented control option
            ->assertSee('Current draft') // Segmented control option
            ->assertSee('sandbox=""', false);
    }

    public function test_device_toggle_changes_only_wrapper_class()
    {
        $user = User::factory()->create(['role' => UserRole::Owner->value]);
        $business = Business::factory()->create(['owner_user_id' => $user->id]);

        $page = Page::create([
            'business_id' => $business->id,
            'slug' => 'toggle-page',
            'title' => 'Toggle',
            'is_published' => false,
        ]);

        $component = Livewire::actingAs($user)
            ->test(Pages::class, ['editingPageId' => $page->id]);

        $component->assertSet('deviceWidth', 'desktop');
        $htmlDesktop = $component->get('previewHtml');

        $component->call('setDeviceWidth', 'phone');
        $component->assertSet('deviceWidth', 'phone');

        $htmlPhone = $component->get('previewHtml');

        $this->assertSame($htmlDesktop, $htmlPhone);
    }

    public function test_no_banned_words_in_response()
    {
        $user = User::factory()->create(['role' => UserRole::Owner->value]);
        $business = Business::factory()->create(['owner_user_id' => $user->id]);

        $page = Page::create([
            'business_id' => $business->id,
            'slug' => 'banned-words-page',
            'title' => 'Banned',
            'is_published' => false,
        ]);

        $responseList = $this->actingAs($user)->get(route('x-103.pages'))->content();
        $responseEdit = $this->actingAs($user)->get(route('x-103.pages', ['edit' => $page->id]))->content();

        $bannedWords = ['pipeline', 'ledger', 'contrast_ratio', 'niche', 'Page ID', 'blocks drafted', 'indigo', 'slate-950'];

        foreach ($bannedWords as $word) {
            $this->assertStringNotContainsString($word, $responseList);
            $this->assertStringNotContainsString($word, $responseEdit);
        }
    }
}
