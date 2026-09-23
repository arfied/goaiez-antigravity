<?php

declare(strict_types=1);

namespace Tests\Modules\CAgent\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CAgent\Models\AgentTurn;
use App\Modules\CAgent\Ui\GroundcheckScreen;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class GroundcheckScreenScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-agent.groundcheck-screen'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No agent turns yet.')
            ->assertSee('0 answered · 0 refused · 0 handed off');

        AgentTurn::create(['business_id' => $biz->id, 'turn_number' => 1, 'user_message' => 'Distinctive question 4530', 'agent_reply' => 'Distinctive answer 4530']);
        AgentTurn::create(['business_id' => $biz->id, 'turn_number' => 2, 'user_message' => 'Distinctive refusal 4531', 'agent_reply' => '', 'status' => 'refused', 'refusal_code' => 'UNDER_18']);

        $this->get(route('c-agent.groundcheck-screen'))
            ->assertOk()
            ->assertSee('Distinctive question 4530')
            ->assertSeeText('Turn 2: refused (UNDER_18)')
            ->assertSee('1 answered · 1 refused · 0 handed off')
            ->assertDontSee('No agent turns yet.');

        Livewire::test(GroundcheckScreen::class)->assertOk();
    }

    public function test_can_ask_agent(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        Livewire::test(GroundcheckScreen::class)
            ->set('userMessage', 'Hello there')
            ->call('askAgent')
            ->assertSet('error', null);

        $turn = AgentTurn::where('business_id', $biz->id)->first();

        $this->assertDatabaseHas((new AgentTurn)->getTable(), [
            'business_id' => $biz->id,
            'user_message' => 'Hello there',
            'status' => 'answered',
            'agent_reply' => 'Hello! How can I help you today?',
        ]);

        $this->get(route('c-agent.groundcheck-screen'))
            ->assertSee('Hello there')
            ->assertSee('1 answered · 0 refused · 0 handed off');

        $this->get(route('c-agent.thread'))
            ->assertSee('Hello there');
    }

    public function test_refuses_empty_message(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        Livewire::test(GroundcheckScreen::class)
            ->call('askAgent')
            ->assertSet('error', 'Message cannot be empty.');

        $this->assertDatabaseMissing((new AgentTurn)->getTable(), [
            'business_id' => $biz->id,
        ]);
    }
}
