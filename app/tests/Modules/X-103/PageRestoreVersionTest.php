<?php

namespace Tests\Modules\X103;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X103\Actions\PageRestoreVersionAction;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVersion;
use App\Modules\X103\Ui\Pages;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class PageRestoreVersionTest extends TestCase
{
    private function pageWithAnOldVersion(?array $draftMeta = null): array
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['name' => 'Restore Tenant', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
            'is_published' => true,
            'draft_blocks' => [['type' => 'hero', 'headline' => 'Distinctive draft today 4961']],
            'draft_meta' => $draftMeta,
        ]);

        $old = PageVersion::create([
            'business_id' => $biz->id,
            'page_id' => $page->id,
            'commit_id' => 'commit_old_4962',
            'content_blocks' => [
                ['type' => 'hero', 'headline' => 'Distinctive old headline 4963'],
                ['type' => 'about', 'text' => 'Distinctive old about 4964'],
                ['type' => 'faq', 'question' => 'Q?', 'answer' => 'A.'],
                ['type' => 'pixel_script'],
                ['type' => 'seo_tags'],
            ],
        ]);

        $current = PageVersion::create([
            'business_id' => $biz->id,
            'page_id' => $page->id,
            'commit_id' => 'commit_current_4965',
            'content_blocks' => [['type' => 'hero', 'headline' => 'Distinctive current 4966']],
        ]);
        $page->update(['current_version_id' => $current->id]);

        return [$owner, $biz, $page, $old];
    }

    public function test_restore_brings_back_every_section_live_and_in_the_draft(): void
    {
        [, $biz, $page, $old] = $this->pageWithAnOldVersion();

        $result = app(PageRestoreVersionAction::class)->handle($biz->id, $page->id, $old->id);
        $this->assertSame('published', $result['status']);

        $page->refresh();
        $this->assertSame(['hero', 'about', 'faq'], array_map(fn ($b) => $b['type'], $page->draft_blocks));
        $this->assertSame('Distinctive old headline 4963', $page->draft_blocks[0]['headline']);

        $live = PageVersion::find($page->current_version_id);
        $liveTypes = array_map(fn ($b) => $b['type'], $live->content_blocks);
        $this->assertContains('hero', $liveTypes);
        $this->assertContains('about', $liveTypes);
        $this->assertContains('pixel_script', $liveTypes);
        $this->assertSame(1, count(array_keys($liveTypes, 'pixel_script', true)));
    }

    public function test_restore_puts_the_replaced_draft_on_the_undo_stack(): void
    {
        [, $biz, $page, $old] = $this->pageWithAnOldVersion();

        app(PageRestoreVersionAction::class)->handle($biz->id, $page->id, $old->id);

        $page->refresh();
        $undo = $page->draft_meta['undo'];
        $this->assertSame('Distinctive draft today 4961', $undo[array_key_last($undo)]['blocks'][0]['headline']);
    }

    public function test_restore_is_refused_while_a_proposal_is_open(): void
    {
        [$owner, $biz, $page, $old] = $this->pageWithAnOldVersion(['pending_edit' => ['request' => 'x', 'blocks' => [], 'explanation' => 'y']]);
        $versionsBefore = PageVersion::where('page_id', $page->id)->count();

        Livewire::actingAs($owner)->test(Pages::class)
            ->call('restore', $page->id, $old->id)
            ->assertSet('error', 'Apply or discard the AI proposal first.');

        $page->refresh();
        $this->assertSame('Distinctive draft today 4961', $page->draft_blocks[0]['headline']);
        $this->assertSame($versionsBefore, PageVersion::where('page_id', $page->id)->count());
    }
}
