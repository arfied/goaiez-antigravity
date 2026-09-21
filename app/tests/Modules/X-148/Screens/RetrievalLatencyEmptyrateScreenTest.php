<?php

declare(strict_types=1);

namespace Tests\Modules\X148\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X148\Ui\RetrievalLatencyEmptyrate;
use Livewire\Livewire;
use Tests\TestCase;

class RetrievalLatencyEmptyrateScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-148.retrieval-latency-emptyrate'))->assertOk();

        Livewire::test(RetrievalLatencyEmptyrate::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-148.retrieval-latency-emptyrate.admin'))->assertOk();

        Livewire::test(RetrievalLatencyEmptyrate::class)->assertOk();

    }

    public function test_can_index_chunk_and_search(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(RetrievalLatencyEmptyrate::class)
            ->set('chunkTitle', 'Test Title')
            ->set('chunkText', 'Test Text')
            ->call('indexChunk')
            ->assertSet('error', null)
            ->assertSet('success', 'Indexed knowledge chunk. This feeds the knowledge base; nothing downstream is wired to it yet.');

        $this->assertDatabaseHas('knowledge_chunks', [
            'business_id' => $biz->id,
            'title' => 'Test Title',
            'chunk_text' => 'Test Text',
        ]);

        Livewire::test(RetrievalLatencyEmptyrate::class)
            ->set('searchQuery', 'Test Query')
            ->call('search')
            ->assertSet('error', null);

        $this->assertDatabaseHas('retrieval_cache', [
            'business_id' => $biz->id,
            'query_hash' => md5($biz->id.':Test Query'),
        ]);

        $this->get(route('x-148.retrieval-latency-emptyrate'))
            ->assertOk()
            ->assertSee('Test Query')
            ->assertDontSee('No cached retrieval queries.');
    }

    public function test_refuses_empty_input(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(RetrievalLatencyEmptyrate::class)
            ->set('chunkTitle', '')
            ->set('chunkText', '')
            ->call('indexChunk')
            ->assertSet('error', 'Title and text are required.');

        $this->assertDatabaseMissing('knowledge_chunks', [
            'business_id' => $biz->id,
        ]);

        Livewire::test(RetrievalLatencyEmptyrate::class)
            ->set('searchQuery', '')
            ->call('search')
            ->assertSet('error', 'Search query is required.');

        $this->assertDatabaseMissing('retrieval_cache', [
            'business_id' => $biz->id,
        ]);
    }
}
