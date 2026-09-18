<?php

declare(strict_types=1);

namespace Tests\Modules\X118\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X118\Ui\SameFlow;
use Livewire\Livewire;
use Tests\TestCase;

class SameFlowScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        \App\Support\Tenancy::setUser($owner->id);
        \App\Modules\X118\Models\OnboardingRun::create([
            'business_id' => $biz->id,
            'business_name' => 'Demo Biz Same Flow',
            'contact_phone' => '555-0100',
        ]);
        \App\Support\Tenancy::forget();

        $this->get(route('x-118.same-flow'))
            ->assertOk()
            ->assertSee('Standard Flow')
            ->assertSee('Demo Biz Same Flow')
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console');

        Livewire::test(SameFlow::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-118.same-flow.admin'))->assertOk();

        Livewire::test(SameFlow::class)->assertOk();
    }
}
