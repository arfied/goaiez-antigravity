<?php

declare(strict_types=1);

namespace Tests\Modules\X102\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X102\Models\ChatLead;
use App\Modules\X102\Models\ChatSession;
use App\Modules\X102\Ui\CustomerfacingWidget;
use App\Modules\X121\Models\Person;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerfacingWidgetScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        $this->actingAs($owner)->get(route('x-102.customerfacing-widget'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No chat sessions yet.')
            ->assertSee('0 sessions · 0 active')
            ->assertSee('chat-widget-container');

        Tenancy::setUser($owner->id);
        ChatSession::create([
            'business_id' => $biz->id,
            'session_token' => 'sess_distinctive_4550',
            'visitor_ip' => '203.0.113.50',
            'status' => 'active',
            'rage_clicks_count' => 0,
            'is_ai_capped' => false,
        ]);
        ChatSession::create([
            'business_id' => $biz->id,
            'session_token' => 'sess_distinctive_4551',
            'visitor_ip' => '203.0.113.50',
            'status' => 'closed',
            'rage_clicks_count' => 0,
            'is_ai_capped' => false,
        ]);
        Tenancy::forget();

        $this->actingAs($owner)->get(route('x-102.customerfacing-widget'))
            ->assertOk()
            ->assertSee('sess_distinctive_4550')
            ->assertSee('sess_distinctive_4551')
            ->assertSee('2 sessions · 1 active')
            ->assertSee('[closed]')
            ->assertDontSee('No chat sessions yet.');

        Livewire::test(CustomerfacingWidget::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-102.customerfacing-widget.admin'))->assertOk();

        Livewire::test(CustomerfacingWidget::class)->assertOk();
    }

    public function test_escalating_a_session_with_a_contact_hands_it_to_a_person(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::setUser($owner->id);
        $session = ChatSession::create([
            'business_id' => $biz->id,
            'session_token' => 'sess_distinctive_escalate',
            'visitor_ip' => '203.0.113.50',
            'status' => 'active',
            'rage_clicks_count' => 0,
            'is_ai_capped' => false,
        ]);
        ChatLead::create([
            'business_id' => $biz->id,
            'chat_session_id' => $session->id,
            'person_id' => Person::create(['business_id' => $biz->id, 'first_name' => 'Test', 'phone' => '5551234'])->id,
            'name' => 'Alice',
            'phone' => '555-1234',
            'email' => 'alice@example.com',
            'message' => 'Help',
            'form_type' => 'live_chat',
            'consent_logged_at' => now(),
        ]);
        Tenancy::set($biz->id);
        Livewire::actingAs($owner)->test(CustomerfacingWidget::class, ['businessId' => $biz->id])
            ->call('escalate', $session->id)
            ->assertHasNoErrors();

        $this->assertEquals('escalated', $session->fresh()->status);
        Tenancy::forget();

        $this->actingAs($owner)->get(route('x-102.customerfacing-widget'))
            ->assertSee('[escalated]')
            ->assertSee('1 escalated');
    }

    public function test_a_session_with_no_contact_cannot_be_handed_to_a_person(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::setUser($owner->id);
        $session = ChatSession::create([
            'business_id' => $biz->id,
            'session_token' => 'sess_distinctive_nocontact',
            'visitor_ip' => '203.0.113.50',
            'status' => 'active',
            'rage_clicks_count' => 0,
            'is_ai_capped' => false,
        ]);
        Tenancy::forget();

        Tenancy::set($biz->id);
        Livewire::actingAs($owner)->test(CustomerfacingWidget::class, ['businessId' => $biz->id])
            ->call('escalate', $session->id)
            ->assertHasNoErrors()
            ->assertSee('no way to reach them');

        $this->assertEquals('active', $session->fresh()->status);
        Tenancy::forget();
    }
}
