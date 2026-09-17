<?php

declare(strict_types=1);

namespace Tests\Modules\X118\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X118\Models\OnboardingRun;
use App\Modules\X118\Ui\Today;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class TodayScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-118.today'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No onboarding runs yet.');

        Tenancy::setUser($owner->id);
        OnboardingRun::create([
            'business_id' => $biz->id,
            'business_name' => 'Distinctive Plumbing 4631',
            'contact_phone' => '+15550104631',
            'provisioned_number' => '+15550204631',
            'status' => 'live',
            'ttfm_ms' => 950,
            'asked_fields_count' => 2,
        ]);
        Tenancy::forget();

        $this->get(route('x-118.today'))
            ->assertOk()
            ->assertSee('Distinctive Plumbing 4631')
            ->assertSee('950')
            ->assertSee('+15550204631')
            ->assertSee('live')
            ->assertDontSee('No onboarding runs yet.');

        Livewire::test(Today::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-118.today.admin'))->assertOk();

        Livewire::test(Today::class)->assertOk();
    }
}
