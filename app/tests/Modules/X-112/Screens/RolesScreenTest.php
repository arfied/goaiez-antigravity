<?php

declare(strict_types=1);

namespace Tests\Modules\X112\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X112\Models\Agency;
use App\Modules\X112\Models\StaffRole;
use App\Modules\X112\Ui\Roles;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class RolesScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::setUser($owner->id);
        $agency = Agency::create([
            'business_id' => $biz->id,
            'agency_name' => 'Demo Agency',
            'whitelabel_domain' => 'demo.example',
            'agency_mode' => 'full_service',
        ]);
        StaffRole::create([
            'business_id' => $biz->id,
            'agency_id' => $agency->id,
            'user_id' => $owner->id,
            'role' => 'account_manager',
            'is_active' => true,
        ]);
        Tenancy::forget();

        $this->get(route('x-112.roles'))
            ->assertOk()
            ->assertSee('account_manager');

        Livewire::test(Roles::class)->assertOk()->assertSee('account_manager');
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-112.roles.admin'))->assertOk();

        Livewire::test(Roles::class)->assertOk();
    }
}
