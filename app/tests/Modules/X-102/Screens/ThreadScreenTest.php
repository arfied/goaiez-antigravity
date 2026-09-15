<?php

declare(strict_types=1);

namespace Tests\Modules\X102\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X102\Models\ChatSession;
use App\Modules\X102\Models\ChatTurn;
use App\Modules\X102\Ui\Thread;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ThreadScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-102.thread'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No chat turns yet.');

        Tenancy::setUser($owner->id);
        $session = ChatSession::create([
            'business_id' => $biz->id,
            'session_token' => 'sess_distinctive_4485',
            'status' => 'active',
            'rage_clicks_count' => 0,
            'is_ai_capped' => false,
        ]);
        ChatTurn::create([
            'business_id' => $biz->id,
            'chat_session_id' => $session->id,
            'author_type' => 'visitor',
            'message' => 'Distinctive message 4485',
        ]);
        Tenancy::forget();

        $this->get(route('x-102.thread'))
            ->assertOk()
            ->assertSee('visitor: Distinctive message 4485')
            ->assertDontSee('No chat turns yet.');

        Livewire::test(Thread::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-102.thread.admin'))->assertOk();

        Livewire::test(Thread::class)->assertOk();
    }
}
