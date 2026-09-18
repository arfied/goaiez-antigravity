<?php

declare(strict_types=1);

namespace Tests\Modules\X82\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X82\Models\Rate;
use App\Modules\X82\Ui\RateRegistryView;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class RateRegistryViewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-82.rate-registry'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No rates in the registry.');

        Tenancy::setUser($owner->id);
        Rate::create([
            'business_id' => $biz->id,
            'rate_code' => 'DISTINCTIVE_RATE_4471',
            'amount_cents' => 15000,
            'currency' => 'USD',
            'current_version' => 1,
            'is_active' => true,
            'is_sample' => false,
        ]);
        Tenancy::forget();

        $this->get(route('x-82.rate-registry'))
            ->assertOk()
            ->assertSee('DISTINCTIVE_RATE_4471')
            ->assertSee('150.00')
            ->assertDontSee('No rates in the registry.');

        Livewire::test(RateRegistryView::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-82.rate-registry.admin'))->assertOk();

        Livewire::test(RateRegistryView::class)->assertOk();
    }
}
