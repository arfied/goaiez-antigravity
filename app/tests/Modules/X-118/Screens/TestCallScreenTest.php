<?php

declare(strict_types=1);

namespace Tests\Modules\X118\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X118\Models\OnboardingRun;
use App\Modules\X118\Ui\TestCall;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class TestCallScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::setUser($owner->id);
        OnboardingRun::create([
            'business_id' => $biz->id,
            'business_name' => 'Demo Biz Test Call',
            'contact_phone' => '555-0100',
        ]);
        Tenancy::forget();

        $this->get(route('x-118.test-call'))
            ->assertOk()
            ->assertSee('Direct Test Call')
            ->assertSee('Demo Biz Test Call')
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console');

        Livewire::test(TestCall::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-118.test-call.admin'))->assertOk();

        Livewire::test(TestCall::class)->assertOk();
    }
}
