<?php

declare(strict_types=1);

namespace Tests\Modules\X118\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X118\Models\OnboardingRun;
use App\Modules\X118\Models\OnboardingStep;
use App\Modules\X118\Ui\Groundcheck;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class GroundcheckScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-118.groundcheck'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No checks recorded yet.');

        Tenancy::setUser($owner->id);
        $run = OnboardingRun::create([
            'business_id' => $biz->id,
            'business_name' => 'Demo Biz',
            'contact_phone' => '555-0100',
        ]);
        OnboardingStep::create([
            'business_id' => $biz->id,
            'run_id' => $run->id,
            'step_name' => 'distinctive_check_4649',
            'is_hard_stop' => false,
        ]);
        Tenancy::forget();

        $this->get(route('x-118.groundcheck'))
            ->assertOk()
            ->assertSee('distinctive_check_4649')
            ->assertDontSee('No checks recorded yet.');

        Livewire::test(Groundcheck::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-118.groundcheck.admin'))->assertOk();

        Livewire::test(Groundcheck::class)->assertOk();
    }
}
