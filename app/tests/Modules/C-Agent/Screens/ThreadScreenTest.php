<?php

declare(strict_types=1);

namespace Tests\Modules\CAgent\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CAgent\Models\AgentTurn;
use App\Modules\CAgent\Ui\Thread;
use Livewire\Livewire;
use Tests\TestCase;

class ThreadScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-agent.thread'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No agent turns recorded.');

        Livewire::test(Thread::class)->assertOk();
    }

    /**
     * Proves the component renders the tenant's agent turns by seeing their messages.
     */
    public function test_shows_tenant_agent_turns(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        AgentTurn::create([
            'business_id' => $biz->id,
            'turn_number' => 1,
            'user_message' => 'Hello from user',
            'agent_reply' => 'Hello from agent',
            'status' => 'completed',
        ]);

        $this->get(route('c-agent.thread'))
            ->assertSee('Hello from user')
            ->assertSee('Hello from agent');

        Livewire::test(Thread::class)
            ->assertSee('Hello from user')
            ->assertSee('Hello from agent');
    }
}
