<?php

declare(strict_types=1);

namespace Tests\Modules\X124\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X124\Ui\TodaysRecommendationStrip;
use Livewire\Livewire;
use Tests\TestCase;

class TodaysRecommendationStripScreenTest extends TestCase
{
    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-124.todays-recommendation-strip.admin'))->assertOk();

        Livewire::test(TodaysRecommendationStrip::class)->assertOk();
    }

    public function test_can_recommend(): void
    {
        $admin = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($admin);
        $biz = $this->provisionTenant(['owner_user_id' => $admin->id]);

        Livewire::test(\App\Modules\X124\Ui\ChatDockEvery::class)
            ->set('utterance', 'Hello assistant')
            ->call('ask');

        $session = \App\Modules\X124\Models\AssistantSession::first();

        Livewire::test(TodaysRecommendationStrip::class)
            ->call('load')
            ->set('selectedSessionId', $session->id)
            ->set('recommendTitle', 'Test Title')
            ->set('actionKey', 'test_action')
            ->call('recommend')
            ->assertSet('error', null)
            ->assertSet('success', 'Recorded recommendation. This feeds the recommendations strip; nothing downstream is wired to it yet.');

        $this->assertDatabaseHas('assistant_recommendations', [
            'business_id' => $biz->id,
            'session_id' => $session->id,
            'title' => 'Test Title',
            'action_key' => 'test_action',
            'status' => 'active',
        ]);

        $this->get(route('x-124.todays-recommendation-strip.admin'))
            ->assertOk()
            ->assertSee('Test Title');
    }

    public function test_refuses_empty_input(): void
    {
        $admin = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($admin);
        $biz = $this->provisionTenant(['owner_user_id' => $admin->id]);

        Livewire::test(TodaysRecommendationStrip::class)
            ->call('load')
            ->set('selectedSessionId', 0)
            ->set('recommendTitle', '')
            ->set('actionKey', '')
            ->call('recommend')
            ->assertSet('error', 'Session, title, and action key are required.');

        $this->assertDatabaseMissing('assistant_recommendations', [
            'business_id' => $biz->id,
        ]);
    }
}
