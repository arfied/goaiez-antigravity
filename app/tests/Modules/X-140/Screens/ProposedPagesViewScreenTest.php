<?php

declare(strict_types=1);

namespace Tests\Modules\X140\Screens;

use App\Modules\X140\Models\ContentTopic;
use App\Modules\X140\Ui\ProposedPagesView;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ProposedPagesViewScreenTest extends TestCase
{
    public function test_can_submit_topic_and_see_on_screen()
    {
        $biz = TestCase::provisionTenant(['name' => 'Topic Tenant']);
        Tenancy::set($biz->id);
        $this->actingAs($biz->owner);

        Livewire::test(ProposedPagesView::class)
            ->set('topicTitle', 'How to fix a leaky faucet')
            ->set('clusterKey', 'plumbing_issues')
            ->call('submit')
            ->assertSet('error', null)
            ->assertSet('success', "Recorded proposed page 'How to fix a leaky faucet'. The row is created unpublished; nothing downstream is wired to it yet.");

        $this->assertDatabaseHas((new ContentTopic())->getTable(), [
            'business_id' => $biz->id,
            'topic_title' => 'How to fix a leaky faucet',
            'is_published' => false,
        ]);

        $this->withSession(['tenant_id' => $biz->id])
            ->get('/app/x-140/proposed-pages')
            ->assertSee('How to fix a leaky faucet')
            ->assertDontSee('No proposed pages yet.');
    }

    public function test_refuses_invalid_input()
    {
        $biz = TestCase::provisionTenant(['name' => 'Topic Tenant']);
        Tenancy::set($biz->id);
        $this->actingAs($biz->owner);

        Livewire::test(ProposedPagesView::class)
            ->set('topicTitle', '')
            ->call('submit')
            ->assertSet('error', 'Topic title is required.');

        $this->assertDatabaseMissing((new ContentTopic())->getTable(), [
            'business_id' => $biz->id,
        ]);
    }
}
