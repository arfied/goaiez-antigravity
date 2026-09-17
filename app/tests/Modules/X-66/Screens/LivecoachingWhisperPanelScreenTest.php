<?php

declare(strict_types=1);

namespace Tests\Modules\X66\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X66\Models\CallAutopsy;
use App\Modules\X66\Models\CallSession;
use App\Modules\X66\Ui\LivecoachingWhisperPanel;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class LivecoachingWhisperPanelScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-66.livecoaching-whisper-panel'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No coaching notes yet.');

        Tenancy::setUser($owner->id);
        $session = CallSession::create([
            'business_id' => $biz->id,
            'call_sid' => 'demo-none-CA4644',
            'from_phone' => '+15125554644',
            'to_phone' => '+15125550100',
            'status' => 'completed',
            'latency_ms' => 4644,
        ]);
        CallAutopsy::create([
            'business_id' => $biz->id,
            'call_session_id' => $session->id,
            'sentiment' => 'negative',
            'coaching_notes' => 'Distinctive coaching note 4644',
        ]);
        Tenancy::forget();

        $this->get(route('x-66.livecoaching-whisper-panel'))
            ->assertOk()
            ->assertSee('Distinctive coaching note 4644')
            ->assertSee('negative')
            ->assertDontSee('No coaching notes yet.');

        Livewire::test(LivecoachingWhisperPanel::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-66.livecoaching-whisper-panel.admin'))->assertOk();

        Livewire::test(LivecoachingWhisperPanel::class)->assertOk();
    }
}
