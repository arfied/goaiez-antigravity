<?php

declare(strict_types=1);

namespace Tests\Modules\X66\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X66\Models\CallSession;
use App\Modules\X66\Ui\LatencyP50p95Per;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class LatencyP50p95PerScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-66.latency-p50p95-per'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No calls to time yet.');

        Tenancy::setUser($owner->id);
        CallSession::create([
            'business_id' => $biz->id,
            'call_sid' => 'demo-none-CA4644',
            'from_phone' => '+15125554644',
            'to_phone' => '+15125550100',
            'status' => 'completed',
            'latency_ms' => 4644,
        ]);
        Tenancy::forget();

        $this->get(route('x-66.latency-p50p95-per'))
            ->assertOk()
            ->assertSee('+15125554644')
            ->assertSee('4644ms')
            ->assertDontSee('No calls to time yet.');

        Livewire::test(LatencyP50p95Per::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-66.latency-p50p95-per.admin'))->assertOk();

        Livewire::test(LatencyP50p95Per::class)->assertOk();
    }
}
