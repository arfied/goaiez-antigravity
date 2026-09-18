<?php

declare(strict_types=1);

namespace Tests\Modules\CAgent\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CAgent\Models\AgentTurn;
use App\Modules\CAgent\Ui\GroundcheckScreen;
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
}
