<?php

declare(strict_types=1);

namespace Tests\Modules\X102\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X102\Models\ChatLead;
use App\Modules\X102\Models\ChatSession;
use App\Modules\X102\Ui\OfflineFormInbox;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class OfflineFormInboxScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-102.offline-form-inbox'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No captured chat leads.');

        Tenancy::setUser($owner->id);
        $session = ChatSession::create([
            'business_id' => $biz->id,
            'session_token' => 'sess_distinctive_4476_' . uniqid(),
            'status' => 'active',
            'rage_clicks_count' => 0,
            'is_ai_capped' => false,
        ]);
        ChatLead::create([
            'business_id' => $biz->id,
            'chat_session_id' => $session->id,
            'name' => 'Distinctive Lead 4476',
            'phone' => '+15125554476',
            'form_type' => 'offline_capped_form',
        ]);
        Tenancy::forget();

        $this->get(route('x-102.offline-form-inbox'))
            ->assertOk()
            ->assertSee('Distinctive Lead 4476')
            ->assertSee('+15125554476')
            ->assertSee('[offline_capped_form]')
            ->assertDontSee('No captured chat leads.');

        Livewire::test(OfflineFormInbox::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-102.offline-form-inbox.admin'))->assertOk();

        Livewire::test(OfflineFormInbox::class)->assertOk();
    }
}
