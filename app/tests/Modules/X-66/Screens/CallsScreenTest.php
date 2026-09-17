<?php

declare(strict_types=1);

namespace Tests\Modules\X66\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X66\Models\CallSession;
use App\Modules\X66\Models\CallTurn;
use App\Modules\X66\Ui\Calls;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class CallsScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-66.calls'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No calls yet')
            ->assertDontSee('019-8372');

        Tenancy::setUser($owner->id);
        $session = CallSession::create([
            'business_id' => $biz->id,
            'call_sid' => 'CA_distinctive_4609',
            'from_phone' => '+15125554609',
            'to_phone' => '+15125550100',
            'status' => 'completed',
            'latency_ms' => 310,
        ]);
        CallTurn::create([
            'business_id' => $biz->id,
            'session_id' => $session->id,
            'turn_index' => 1,
            'speaker' => 'caller',
            'transcript' => 'Distinctive transcript 4609',
        ]);
        Tenancy::forget();

        $this->get(route('x-66.calls'))
            ->assertOk()
            ->assertSee('+15125554609')
            ->assertSee('Completed')
            ->assertSee('310ms')
            ->assertSee('Transcript')
            ->assertDontSee('No calls yet');

        Livewire::test(Calls::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-66.calls.admin'))->assertOk();

        Livewire::test(Calls::class)->assertOk();
    }
}
