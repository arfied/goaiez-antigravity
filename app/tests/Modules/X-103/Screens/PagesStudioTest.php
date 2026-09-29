<?php

namespace Tests\Modules\X103\Screens;

use App\Enums\AiModel;
use App\Enums\AiTask;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\IndustryStartingPoint;
use App\Models\Location;
use App\Models\User;
use App\Modules\X103\Actions\SiteBuildRunAction;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Ui\Pages;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class PagesStudioTest extends TestCase
{
    use RefreshesTenantDatabase;

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

    public function test_no_website_build_drafts_home_page()
    {
        $user = User::factory()->create(['role' => UserRole::Owner->value]);
        $business = Business::factory()->create([
            'owner_user_id' => $user->id,

        ]);
        $location = Location::factory()->create([
            'business_id' => $business->id,
            'website_url' => null, 'primary_phone' => '1234567890', 'primary_phone_confirmed_at' => now(),
        ]);

        $action = app(SiteBuildRunAction::class);
        $result = $action->handle($business->id, $location->id);

        $this->assertEquals('completed', $result['status']);
        $this->assertDatabaseHas('pages', [
            'business_id' => $business->id,
            'slug' => 'home',
        ]);
    }

    public function test_loop_build_choose_look_publish()
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => UserRole::Owner->value]);
        $business = Business::factory()->create(['owner_user_id' => $user->id]);
        $location = Location::factory()->create([
            'business_id' => $business->id,
            'website_url' => null,
            'primary_phone' => '1234567890',
            'primary_phone_confirmed_at' => now(),
        ]);

        Tenancy::set($business->id);
        $business->update(['industry' => 'trades']);
        IndustryStartingPoint::updateOrCreate(
            ['family' => 'trades'],
            [
                'palette' => ['surface' => '#ffffff', 'ink' => '#000000', 'primary' => '#ff0000', 'accent' => '#0000ff'],
                'type_pairing' => ['heading' => 'serif', 'body' => 'sans'],
                'section_order' => ['hero', 'about', 'gallery', 'reviews_strip', 'contact'],
            ]
        );

        $action = app(SiteBuildRunAction::class);
        $action->handle($business->id, $location->id);

        $this->assertDatabaseHas('pages', [
            'business_id' => $business->id,
            'slug' => 'home',
        ]);

        $component = Livewire::actingAs($user)
            ->test(Pages::class)
            ->assertDontSee('No preview');

        $component->call('chooseLook', 'c');
        $business->refresh();
        $this->assertSame('c', $business->site_variant);

        $component->call('publishAll');

        $home = Page::where('business_id', $business->id)->where('slug', 'home')->first();
        $this->assertTrue($home->is_published);
    }

    public function test_site_authoring_tier_is_configured()
    {
        $this->assertSame(AiModel::Gpt4oMini, AiTask::SiteAuthoring->defaultModel());
        $this->assertSame('ai.model.site_authoring', AiTask::SiteAuthoring->settingKey());
    }
}
