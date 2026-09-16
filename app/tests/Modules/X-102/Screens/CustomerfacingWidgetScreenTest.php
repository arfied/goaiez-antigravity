<?php

declare(strict_types=1);

namespace Tests\Modules\X102\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X102\Models\ChatSession;
use App\Modules\X102\Ui\CustomerfacingWidget;
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
}
