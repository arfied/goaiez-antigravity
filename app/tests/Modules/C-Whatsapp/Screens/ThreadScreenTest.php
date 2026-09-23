<?php

declare(strict_types=1);

namespace Tests\Modules\CWhatsapp\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CWhatsapp\Models\WhatsappSession;
use App\Modules\CWhatsapp\Ui\Thread;
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

        $this->get(route('c-whatsapp.thread'))->assertOk()->assertSee('Your account')->assertSee('WhatsApp conversations')->assertDontSee('Internal Platform Console');

        Livewire::test(Thread::class)->assertOk();
    }

    public function test_no_session_is_an_honest_empty_state(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-whatsapp.thread'))->assertOk()->assertSee('WhatsApp inbound is not connected');
    }

    public function test_a_session_of_this_tenant_renders_and_another_tenants_does_not(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        WhatsappSession::create([
            'business_id' => $biz->id,
            'recipient_phone' => '+15125567731',
            'is_window_open' => true,
            'last_inbound_at' => now(),
            'session_window_expires_at' => now()->addDay(),
        ]);

        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id]);

        Tenancy::setUser((int) $ownerB->id);
        Tenancy::set((int) $bizB->id);

        WhatsappSession::create([
            'business_id' => $bizB->id,
            'recipient_phone' => '+15125567732',
            'is_window_open' => true,
            'last_inbound_at' => now(),
            'session_window_expires_at' => now()->addDay(),
        ]);

        Tenancy::setUser((int) $owner->id);
        Tenancy::set((int) $biz->id);

        $this->get(route('c-whatsapp.thread'))
            ->assertOk()
            ->assertSee('+15125567731')
            ->assertSee('window open')
            ->assertDontSee('+15125567732');
    }
}
