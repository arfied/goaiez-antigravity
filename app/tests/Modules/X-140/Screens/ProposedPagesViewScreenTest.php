<?php

declare(strict_types=1);

namespace Tests\Modules\X140\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X140\Models\ContentTopic;
use App\Modules\X140\Models\TopicSource;
use App\Modules\X140\Ui\ProposedPagesView;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ProposedPagesViewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-140.proposed-pages'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No proposed pages yet.');

        Tenancy::setUser($owner->id);
        $topic = ContentTopic::create([
            'business_id' => $biz->id,
            'topic_title' => 'Distinctive topic 4621',
            'slug' => 'distinctive-topic-4621',
            'cluster_key' => 'general_faq',
            'similarity_rate' => 0.500,
            'is_published' => false,
        ]);
        TopicSource::create([
            'business_id' => $biz->id,
            'topic_id' => $topic->id,
            'source_type' => 'customer_inquiry',
            'raw_content' => 'Distinctive question 4622',
        ]);
        Tenancy::forget();

        $this->get(route('x-140.proposed-pages'))
            ->assertOk()
            ->assertSee('Distinctive topic 4621')
            ->assertSee('1 source')
            ->assertSee('draft')
            ->assertDontSee('No proposed pages yet.');

        Livewire::test(ProposedPagesView::class)->assertOk();
    }

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

        $this->assertDatabaseHas((new ContentTopic)->getTable(), [
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

        $this->assertDatabaseMissing((new ContentTopic)->getTable(), [
            'business_id' => $biz->id,
        ]);
    }
}
