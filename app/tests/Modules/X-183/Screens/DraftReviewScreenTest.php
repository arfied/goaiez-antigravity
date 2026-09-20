<?php

declare(strict_types=1);

namespace Tests\Modules\X183\Screens;

use App\Modules\X183\Models\ContentDraft;
use App\Modules\X183\Ui\DraftReview;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class DraftReviewScreenTest extends TestCase
{
    public function test_can_submit_draft_and_see_on_screen()
    {
        $biz = TestCase::provisionTenant(['name' => 'Draft Tenant']);
        Tenancy::set($biz->id);

        Livewire::test(DraftReview::class)
            ->set('title', 'My first draft')
            ->set('bodyText', 'This is a beautiful text for a draft.')
            ->set('isCaseStudy', false)
            ->call('submit')
            ->assertSet('error', null)
            ->assertSet('success', "Recorded draft 'My first draft'. This feeds the draft list; nothing downstream is wired to it yet.");

        $this->assertDatabaseHas((new ContentDraft())->getTable(), [
            'business_id' => $biz->id,
            'title' => 'My first draft',
            'is_published' => false,
        ]);

        $this->actingAs($biz->owner)
            ->withSession(['tenant_id' => $biz->id])
            ->get('/app/x-183/draft-review')
            ->assertSee('My first draft')
            ->assertDontSee('No drafts yet');
    }

    public function test_refuses_invalid_input()
    {
        $biz = TestCase::provisionTenant(['name' => 'Draft Tenant']);
        Tenancy::set($biz->id);

        Livewire::test(DraftReview::class)
            ->set('title', '')
            ->set('bodyText', 'This is a beautiful text for a draft.')
            ->call('submit')
            ->assertSet('error', 'Title is required.');

        Livewire::test(DraftReview::class)
            ->set('title', 'My first draft')
            ->set('bodyText', '')
            ->call('submit')
            ->assertSet('error', 'Body text is required.');

        $this->assertDatabaseMissing((new ContentDraft())->getTable(), [
            'business_id' => $biz->id,
        ]);
    }
}
