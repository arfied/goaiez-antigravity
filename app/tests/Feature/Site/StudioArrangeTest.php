<?php

namespace Tests\Feature\Site;

use App\Enums\UserRole;
use App\Livewire\Site\Studio;
use App\Models\User;
use App\Modules\X103\Models\Page;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class StudioArrangeTest extends TestCase
{
    private const BLOCKS = [
        ['type' => 'hero', 'headline' => 'H', 'subline' => 'S'],
        ['type' => 'about', 'text' => 'A'],
        ['type' => 'services', 'items' => [['name' => 'Roof repair']]],
        ['type' => 'booking_button', 'label' => 'Book', 'url' => 'https://example.com/book'],
    ];

    private function pageFor(array $blocks, ?array $meta = null): Page
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant(['name' => 'StudioArrange', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($business->id);

        return Page::create([
            'business_id' => $business->id, 'slug' => 'p'.rand(), 'title' => 'T',
            'draft_blocks' => $blocks,
            'draft_meta' => $meta,
            'is_published' => false,
        ]);
    }

    private function types(array $blocks): array
    {
        return array_map(fn ($b) => $b['type'], $blocks);
    }

    public function test_a_section_moves_down_and_the_selection_follows_it(): void
    {
        $page = $this->pageFor(self::BLOCKS);

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('selectBlock', 1)
            ->assertSee('Move down')
            ->call('arrangeSection', 'down')
            ->assertSet('error', null)
            ->assertSet('selectedBlockIndex', 2)
            ->assertSet('success', 'Section moved. Undo last change puts it back.');

        $page->refresh();
        $this->assertSame(['hero', 'services', 'about', 'booking_button'], $this->types($page->draft_blocks));
        $this->assertCount(1, $page->draft_meta['undo']);
    }

    public function test_the_top_section_cannot_move_up(): void
    {
        $page = $this->pageFor(self::BLOCKS);

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('selectBlock', 0)
            ->call('arrangeSection', 'up')
            ->assertSet('error', 'That section is already at the top.');

        $page->refresh();
        $this->assertSame($this->types(self::BLOCKS), $this->types($page->draft_blocks));
    }

    public function test_a_removed_section_comes_back_with_undo(): void
    {
        $page = $this->pageFor(self::BLOCKS);

        $lw = Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('selectBlock', 2)
            ->call('arrangeSection', 'remove')
            ->assertSet('selectedBlockIndex', null)
            ->assertSet('success', 'Section removed. Undo last change brings it back.');

        $page->refresh();
        $this->assertSame(['hero', 'about', 'booking_button'], $this->types($page->draft_blocks));

        $lw->call('undo');
        $page->refresh();
        $this->assertSame($this->types(self::BLOCKS), $this->types($page->draft_blocks));
    }

    public function test_a_system_block_is_never_moved_or_removed(): void
    {
        $page = $this->pageFor([...self::BLOCKS, ['type' => 'pixel_script']]);

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('selectBlock', 4)
            ->call('arrangeSection', 'remove')
            ->assertSet('error', 'That cannot be moved or removed.');

        $page->refresh();
        $this->assertCount(5, $page->draft_blocks);
    }

    public function test_arranging_needs_a_selection_and_an_owner(): void
    {
        $page = $this->pageFor(self::BLOCKS);

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('arrangeSection', 'down')
            ->assertSet('error', 'Select a section on the canvas first.');

        $manager = User::factory()->create(['role' => UserRole::Manager]);
        Livewire::actingAs($manager)->test(Studio::class)
            ->set('pageId', $page->id)
            ->call('selectBlock', 1)
            ->call('arrangeSection', 'down')
            ->assertForbidden();

        $page->refresh();
        $this->assertSame($this->types(self::BLOCKS), $this->types($page->draft_blocks));
    }
}
