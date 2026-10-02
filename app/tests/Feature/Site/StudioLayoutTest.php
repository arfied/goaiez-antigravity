<?php

namespace Tests\Feature\Site;

use App\Enums\UserRole;
use App\Livewire\Site\Studio;
use App\Models\User;
use App\Modules\X103\Actions\PageLayoutProposeAction;
use App\Modules\X103\Domain\PageLayouts;
use App\Modules\X103\Models\Page;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class StudioLayoutTest extends TestCase
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
        $business = TestCase::provisionTenant(['name' => 'StudioLayout', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
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

    public function test_a_layout_is_previewed_then_applied_with_an_undo_entry(): void
    {
        $page = $this->pageFor(self::BLOCKS);

        $lw = Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('proposeLayout', 'book')
            ->assertSet('error', null)
            ->assertSee('Previewing layout: Book first')
            ->assertDontSee('Previewing AI proposal');

        $page->refresh();
        $this->assertSame(['hero', 'about', 'services', 'booking_button'], $this->types($page->draft_blocks));
        $this->assertSame(['hero', 'booking_button', 'services', 'about'], $this->types($page->draft_meta['pending_edit']['blocks']));
        $this->assertSame('book', $page->draft_meta['pending_edit']['layout']);

        $lw->call('applyProposal');

        $page->refresh();
        $this->assertSame(['hero', 'booking_button', 'services', 'about'], $this->types($page->draft_blocks));
        $this->assertArrayNotHasKey('pending_edit', $page->draft_meta);
        $undo = $page->draft_meta['undo'];
        $this->assertSame(['hero', 'about', 'services', 'booking_button'], $this->types($undo[array_key_last($undo)]['blocks']));
    }

    public function test_no_layout_adds_removes_or_rewrites_a_block(): void
    {
        $page = $this->pageFor(self::BLOCKS);
        $action = app(PageLayoutProposeAction::class);

        $this->assertSame(['book', 'proof', 'story', 'services', 'industry'], array_keys(PageLayouts::LAYOUTS));

        foreach (array_keys(PageLayouts::LAYOUTS) as $layout) {
            $page->update(['draft_meta' => null]);
            $res = $action->handle($page->business_id, $page->id, $layout);
            $page->refresh();

            $proposed = $res['status'] === 'proposed' ? $page->draft_meta['pending_edit']['blocks'] : $page->draft_blocks;
            $this->assertContains($res['status'], ['proposed', 'unchanged'], $layout);
            $this->assertEqualsCanonicalizing(self::BLOCKS, $proposed, $layout);
        }
    }

    public function test_a_layout_is_refused_while_a_proposal_is_pending(): void
    {
        $pending = ['request' => 'x', 'blocks' => self::BLOCKS, 'explanation' => 'y'];
        $page = $this->pageFor(self::BLOCKS, ['pending_edit' => $pending]);

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('proposeLayout', 'story')
            ->assertSet('error', 'Apply or discard the proposal you are previewing first.');

        $page->refresh();
        $this->assertSame($pending, $page->draft_meta['pending_edit']);
    }

    public function test_a_page_already_in_that_order_is_left_alone(): void
    {
        $page = $this->pageFor([self::BLOCKS[0], self::BLOCKS[3], self::BLOCKS[2], self::BLOCKS[1]]);

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('proposeLayout', 'book')
            ->assertSet('success', 'Your page is already in that order.');

        $page->refresh();
        $this->assertNull($page->draft_meta['pending_edit'] ?? null);
    }

    public function test_an_unknown_layout_and_a_manager_are_refused(): void
    {
        $page = $this->pageFor(self::BLOCKS);

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('proposeLayout', 'nope')
            ->assertSet('error', 'There is no layout by that name.');

        $manager = User::factory()->create(['role' => UserRole::Manager]);
        Livewire::actingAs($manager)->test(Studio::class)
            ->set('pageId', $page->id)
            ->call('proposeLayout', 'book')
            ->assertForbidden();
    }
}
