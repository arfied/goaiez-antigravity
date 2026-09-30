<?php

namespace Tests\Feature\Site;

use App\Enums\UserRole;
use App\Livewire\Site\Studio;
use App\Models\User;
use App\Modules\X103\Models\Page;
use App\Support\Tenancy;
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
}
