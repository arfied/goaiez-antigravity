<?php

namespace Tests\Feature\Site;

use App\Enums\UserRole;
use App\Livewire\Site\Studio;
use App\Models\User;
use App\Modules\X103\Models\Page;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class StudioInlineEditTest extends TestCase
{
    private const BLOCKS = [
        ['type' => 'hero', 'headline' => 'Old headline', 'subline' => 'S'],
        ['type' => 'about', 'text' => 'A'],
        ['type' => 'booking_button', 'label' => 'Book', 'url' => 'https://example.com/book'],
        ['type' => 'faq', 'items' => [['question' => 'Q?', 'answer' => 'Yes.']]],
    ];

    private function pageFor(array $blocks, ?array $meta = null): Page
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant(['name' => 'StudioInline', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($business->id);

        return Page::create([
            'business_id' => $business->id, 'slug' => 'p'.rand(), 'title' => 'T',
            'draft_blocks' => $blocks,
            'draft_meta' => $meta,
            'is_published' => false,
        ]);
    }

    public function test_typing_on_the_page_saves_to_the_draft_with_an_undo_entry(): void
    {
        $page = $this->pageFor(self::BLOCKS);

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->assertSee('data-field=&quot;headline&quot;', false)
            ->call('editInline', 0, 'headline', '  New headline  ')
            ->assertSet('error', null)
            ->assertSet('success', 'Saved to your draft. Undo last change takes it back.');

        $page->refresh();
        $this->assertSame('New headline', $page->draft_blocks[0]['headline']);
        $this->assertCount(1, $page->draft_meta['undo']);
    }

    public function test_a_link_a_type_or_a_list_faq_cannot_be_set_from_the_canvas(): void
    {
        $page = $this->pageFor(self::BLOCKS);

        foreach ([[2, 'url'], [0, 'type'], [3, 'question'], [9, 'headline']] as [$index, $field]) {
            Livewire::test(Studio::class)
                ->set('pageId', $page->id)
                ->call('editInline', $index, $field, 'x')
                ->assertSet('error', 'That cannot be edited on the page.');
        }

        $page->refresh();
        $this->assertEquals(self::BLOCKS, $page->draft_blocks);
    }

    public function test_an_inline_edit_while_previewing_a_proposal_edits_the_proposal(): void
    {
        $proposed = self::BLOCKS;
        $proposed[0]['headline'] = 'Proposed';
        $page = $this->pageFor(self::BLOCKS, ['pending_edit' => ['request' => 'x', 'blocks' => $proposed, 'explanation' => 'y']]);

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('editInline', 1, 'text', 'Edited in the proposal')
            ->assertSet('success', 'Saved to the proposal you are previewing.');

        $page->refresh();
        $this->assertSame('A', $page->draft_blocks[1]['text']);
        $this->assertSame('Edited in the proposal', $page->draft_meta['pending_edit']['blocks'][1]['text']);
    }

    public function test_an_empty_headline_is_refused_and_a_manager_cannot_edit(): void
    {
        $page = $this->pageFor(self::BLOCKS);

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('editInline', 0, 'headline', '   ')
            ->assertSet('success', null);

        $page->refresh();
        $this->assertSame('Old headline', $page->draft_blocks[0]['headline']);

        $manager = User::factory()->create(['role' => UserRole::Manager]);
        Livewire::actingAs($manager)->test(Studio::class)
            ->set('pageId', $page->id)
            ->call('editInline', 0, 'headline', 'Manager')
            ->assertForbidden();
    }

    public function test_a_list_faq_item_is_typed_over_in_place(): void
    {
        $page = $this->pageFor(self::BLOCKS);

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->assertSee('data-field=&quot;items.0.answer&quot;', false)
            ->call('editInline', 3, 'items.0.answer', 'Yes, every day.')
            ->assertSet('error', null);

        $page->refresh();
        $this->assertSame('Yes, every day.', $page->draft_blocks[3]['items'][0]['answer']);
        $this->assertSame('Q?', $page->draft_blocks[3]['items'][0]['question']);

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('editInline', 3, 'items.5.question', 'x')
            ->assertSet('error', 'That cannot be edited on the page.');

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('editInline', 3, 'items.0.question', '   ')
            ->assertSet('error', 'That text cannot be empty — type something, or press Esc to cancel.');

        $page->refresh();
        $this->assertSame('Q?', $page->draft_blocks[3]['items'][0]['question']);
    }
}
